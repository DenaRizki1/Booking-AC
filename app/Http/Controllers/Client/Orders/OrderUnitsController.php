<?php

namespace App\Http\Controllers\Client\Orders;

use App\Exceptions\UnavailableUnitsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreOrderUnitsRequest;
use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\OrderEstimator;
use App\Services\TransportFeeCalculator;
use App\Support\Format;
use App\Support\OrderDraft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buat order — langkah 1: pilih alamat & unit AC (mockup Client-Order-Unit).
 */
class OrderUnitsController extends Controller
{
    public function __construct(
        private readonly OrderEstimator $estimator,
        private readonly TransportFeeCalculator $transport,
    ) {}

    public function create(Request $request, OrderDraft $draft): View|RedirectResponse
    {
        $client = $this->client($request);
        $addresses = $client->addresses()->orderByDesc('is_default')->oldest()->get();

        if ($addresses->isEmpty()) {
            return redirect()->route('client.addresses.create', ['redirect' => route('client.orders.create', absolute: false)])
                ->with('info', 'Tambahkan alamat beserta titik lokasinya terlebih dahulu.');
        }

        $capacities = AcCapacity::query()->where('is_active', true)->orderBy('sort_order')->orderBy('pk_value')->get();
        $types = AcType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $services = Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        $units = $draft->units();
        if ($units === [] || $request->filled('service')) {
            $preselected = $services->firstWhere('slug', $request->string('service')->toString());
            $defaultCapacity = $capacities->first(fn (AcCapacity $capacity) => (float) $capacity->pk_value === 1.0) ?? $capacities->first();

            $units = [[
                'ac_brand_id' => null,
                'ac_capacity_id' => $defaultCapacity?->id,
                'ac_type_id' => $types->first()?->id,
                'service_id' => $preselected?->id,
            ]];
        }

        $addressId = $addresses->contains('id', $draft->addressId()) ? $draft->addressId() : $addresses->first()->id;

        return view('client.orders.create-units', [
            'addresses' => $addresses->map(fn (CustomerAddress $address) => [
                'id' => $address->id,
                'label' => $address->label,
                'address' => $address->address,
                'distance' => Format::km($address->distance_km),
                'in_range' => $this->transport->isWithinRange((float) $address->distance_km),
            ])->values(),
            'addressId' => $addressId,
            'units' => $units,
            'catalog' => [
                'brands' => AcBrand::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
                'capacities' => $capacities->map(fn (AcCapacity $capacity) => [
                    'id' => $capacity->id,
                    'label' => $capacity->label,
                    'short' => trim(str_replace('PK', '', $capacity->label)),
                ])->values(),
                'types' => $types->map->only(['id', 'name'])->values(),
                'services' => $services->map->only(['id', 'name'])->values(),
                'availability' => $this->availability(),
            ],
        ]);
    }

    /**
     * Simpan draft otomatis (tanpa validasi ketat) agar aman saat refresh.
     */
    public function saveDraft(Request $request, OrderDraft $draft): JsonResponse
    {
        $draft->save(
            $request->integer('customer_address_id') ?: null,
            array_filter((array) $request->input('units', []), 'is_array'),
        );

        return response()->json(['saved' => true]);
    }

    public function store(StoreOrderUnitsRequest $request, OrderDraft $draft): RedirectResponse
    {
        $draft->save($request->integer('customer_address_id'), $request->units(), ready: true);

        return redirect()->route('client.orders.schedule');
    }

    /**
     * Estimasi per unit & total (JSON), memakai OrderEstimator (T-502).
     */
    public function estimate(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $lines = [];
        $totalMin = $totalMax = $totalMinutes = 0;
        $requiresHelper = false;
        $complete = true;

        foreach (array_values(array_filter((array) $request->input('units', []), 'is_array')) as $index => $unit) {
            if (! $this->isComplete($unit)) {
                $lines[] = ['index' => $index, 'available' => false, 'error' => null];
                $complete = false;

                continue;
            }

            try {
                $estimate = $this->estimator->estimate([$unit]);
            } catch (UnavailableUnitsException) {
                $lines[] = ['index' => $index, 'available' => false, 'error' => 'Layanan ini belum tersedia untuk PK & tipe tersebut.'];
                $complete = false;

                continue;
            }

            $totalMin += $estimate->priceMin;
            $totalMax += $estimate->priceMax;
            $totalMinutes += $estimate->totalMinutes;
            $requiresHelper = $requiresHelper || $estimate->requiresHelper;

            $lines[] = [
                'index' => $index,
                'available' => true,
                'error' => null,
                'price_min' => $estimate->priceMin,
                'price_max' => $estimate->priceMax,
                'minutes' => $estimate->totalMinutes,
                'price_label' => Format::rupiahRange($estimate->priceMin, $estimate->priceMax),
                'duration_label' => Format::duration($estimate->totalMinutes),
            ];
        }

        $address = CustomerAddress::query()->whereBelongsTo($client)->find($request->integer('customer_address_id'));
        $fee = $address && $this->transport->isWithinRange((float) $address->distance_km)
            ? $this->transport->feeFor((float) $address->distance_km)
            : null;

        return response()->json([
            'units' => $lines,
            'complete' => $complete && $lines !== [],
            'total' => [
                'price_min' => $totalMin,
                'price_max' => $totalMax,
                'minutes' => $totalMinutes,
                'requires_helper' => $requiresHelper,
                'price_label' => Format::rupiahRange($totalMin, $totalMax),
                'duration_label' => Format::duration($totalMinutes),
            ],
            'transport_fee' => $fee,
            'transport_label' => $fee === null ? null : Format::rupiah($fee),
        ]);
    }

    /**
     * Layanan yang punya harga aktif per kombinasi PK × Tipe: "capacityId-typeId" => [serviceId, …].
     *
     * @return array<string, list<int>>
     */
    private function availability(): array
    {
        $map = [];

        ServicePrice::query()
            ->where('is_active', true)
            ->whereHas('service', fn (Builder $q) => $q->where('is_active', true))
            ->get(['service_id', 'ac_capacity_id', 'ac_type_id'])
            ->each(function (ServicePrice $price) use (&$map) {
                $map["{$price->ac_capacity_id}-{$price->ac_type_id}"][] = $price->service_id;
            });

        return $map;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private function isComplete(array $unit): bool
    {
        foreach (['service_id', 'ac_capacity_id', 'ac_type_id'] as $key) {
            if (! is_numeric($unit[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function client(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
