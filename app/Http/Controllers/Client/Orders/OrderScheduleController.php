<?php

namespace App\Http\Controllers\Client\Orders;

use App\Exceptions\OrderPlacementException;
use App\Exceptions\UnavailableUnitsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreOrderRequest;
use App\Models\AcBrand;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\OrderEstimator;
use App\Services\OrderPlacementService;
use App\Services\TransportFeeCalculator;
use App\Support\BookingCalendar;
use App\Support\Format;
use App\Support\OrderDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buat order — langkah 2 & 3: pilih jadwal, ringkasan, konfirmasi (mockup Client-Order-Jadwal).
 */
class OrderScheduleController extends Controller
{
    public function __construct(
        private readonly OrderEstimator $estimator,
        private readonly BookingCalendar $calendar,
        private readonly TransportFeeCalculator $transport,
    ) {}

    public function create(Request $request, OrderDraft $draft): View|RedirectResponse
    {
        /** @var User $client */
        $client = $request->user();
        $address = CustomerAddress::query()->whereBelongsTo($client)->find($draft->addressId());

        if (! $draft->isReady() || $address === null) {
            return redirect()->route('client.orders.create');
        }

        try {
            $estimate = $this->estimator->estimate($draft->units());
        } catch (UnavailableUnitsException $e) {
            return redirect()->route('client.orders.create')->withErrors(['units' => $e->getMessage()]);
        }

        $settings = CompanySettings::current();
        $brands = AcBrand::query()->pluck('name', 'id');
        $transportFee = $this->transport->feeFor((float) $address->distance_km, $settings);
        $excessKm = max(0, (float) $address->distance_km - (float) $settings->free_radius_km);

        return view('client.orders.create-schedule', [
            'address' => $address,
            'dates' => $this->calendar->dates($estimate->totalMinutes, $estimate->requiresHelper, $settings),
            'lines' => collect($estimate->lines)->map(fn ($line, $index) => [
                'service' => $line->service->name,
                'unit' => implode(' · ', array_filter([
                    $brands[$draft->units()[$index]['ac_brand_id']] ?? null,
                    $line->price->capacity->label,
                    $line->price->type->name,
                ])),
                'price' => Format::rupiahShort($line->priceMin).' – '.str_replace('Rp', '', Format::rupiahShort($line->priceMax)),
            ]),
            'estimate' => $estimate,
            'transportFee' => $transportFee,
            'transportNote' => $transportFee > 0 ? 'Transport ('.Format::km($excessKm).' di luar radius gratis)' : 'Transport (dalam radius gratis)',
            'settings' => $settings,
            'unitCount' => count($estimate->lines),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderDraft $draft, OrderPlacementService $placement): RedirectResponse
    {
        if (! $draft->isReady() || $draft->addressId() === null) {
            return redirect()->route('client.orders.create');
        }

        /** @var User $client */
        $client = $request->user();

        try {
            $order = $placement->place($client, $draft->addressId(), $draft->units(), $request->start(), $request->input('customer_note'));
        } catch (OrderPlacementException $e) {
            // Alamat dipilih di langkah 1; slot jam di langkah ini.
            $target = $e->field === 'customer_address_id' ? redirect()->route('client.orders.create') : back()->withInput();

            return $target->withErrors([$e->field => $e->getMessage()]);
        } catch (UnavailableUnitsException $e) {
            return redirect()->route('client.orders.create')->withErrors(['units' => $e->getMessage()]);
        }

        $draft->clear();

        return redirect()->route('client.orders.show', $order)
            ->with('success', "Order {$order->code} berhasil dibuat. Admin akan segera menugaskan teknisi.");
    }
}
