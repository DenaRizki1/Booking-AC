<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\OrderPlacementException;
use App\Exceptions\UnavailableUnitsException;
use App\Models\AcBrand;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\Estimates\EstimateLine;
use App\Services\Estimates\OrderEstimate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Simpan order baru client: validasi ulang semua (alamat, harga, slot), lalu buat
 * orders + order_items dengan snapshot, kode order, dan log status MENUNGGU.
 */
class OrderPlacementService
{
    public function __construct(
        private readonly OrderEstimator $estimator,
        private readonly ScheduleService $schedule,
        private readonly TransportFeeCalculator $transport,
        private readonly OrderCodeGenerator $codes,
        private readonly OrderStatusService $statuses,
    ) {}

    /**
     * @param  array<int, array{ac_brand_id: int, ac_capacity_id: int, ac_type_id: int, service_id: int}>  $units
     *
     * @throws OrderPlacementException
     * @throws UnavailableUnitsException
     */
    public function place(User $client, int $addressId, array $units, CarbonImmutable $start, ?string $note = null): Order
    {
        $units = array_values($units);

        return DB::transaction(function () use ($client, $addressId, $units, $start, $note) {
            // Kunci baris pengaturan: pembuatan order diproses satu per satu agar slot terakhir tidak dipesan dua kali.
            /** @var CompanySettings $settings */
            $settings = CompanySettings::query()->lockForUpdate()->firstOrFail();

            $address = $this->address($client, $addressId, $settings);
            $estimate = $this->estimator->estimate($units);
            $this->ensureSlotAvailable($start, $estimate, $settings);

            [$end, $blockedUntil] = $this->schedule->window($start, $estimate->totalMinutes, $settings);

            $order = Order::create([
                'code' => $this->codes->next(),
                'order_type' => OrderType::REGULER,
                'user_id' => $client->id,
                'customer_address_id' => $address->id,
                'address_snapshot' => $address->landmark ? "{$address->address} (Patokan: {$address->landmark})" : $address->address,
                'lat' => $address->lat,
                'lng' => $address->lng,
                'distance_km' => $address->distance_km,
                'requires_helper' => $estimate->requiresHelper,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'blocked_until' => $blockedUntil,
                'total_est_minutes' => $estimate->totalMinutes,
                'est_price_min' => $estimate->priceMin,
                'est_price_max' => $estimate->priceMax,
                'transport_fee' => $this->transport->feeFor((float) $address->distance_km, $settings),
                'customer_note' => filled($note) ? trim((string) $note) : null,
                'status' => OrderStatus::MENUNGGU,
            ]);

            $brands = AcBrand::query()->findMany(array_column($units, 'ac_brand_id'))->keyBy('id');

            foreach ($estimate->lines as $index => $line) {
                $order->items()->create($this->itemSnapshot($line, $brands->get($units[$index]['ac_brand_id']), $index, $settings));
            }

            $this->statuses->recordCreated($order, $client);

            return $order;
        });
    }

    private function address(User $client, int $addressId, CompanySettings $settings): CustomerAddress
    {
        $address = CustomerAddress::query()->whereBelongsTo($client)->find($addressId);

        if ($address === null) {
            throw new OrderPlacementException('customer_address_id', 'Alamat tidak ditemukan. Pilih alamat lain.');
        }

        if (! $this->transport->isWithinRange((float) $address->distance_km, $settings)) {
            throw new OrderPlacementException('customer_address_id', 'Alamat ini di luar jangkauan layanan. Pilih alamat lain.');
        }

        return $address;
    }

    private function ensureSlotAvailable(CarbonImmutable $start, OrderEstimate $estimate, CompanySettings $settings): void
    {
        $available = collect($this->schedule->availableSlots($start, $estimate->totalMinutes, $estimate->requiresHelper, $settings))
            ->contains(fn ($slot) => $slot->start->equalTo($start));

        if (! $available) {
            throw new OrderPlacementException('time', "Jam {$start->format('H:i')} pada ".$start->translatedFormat('j F').' baru saja terisi atau tidak tersedia. Silakan pilih jam lain.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function itemSnapshot(EstimateLine $line, ?AcBrand $brand, int $index, CompanySettings $settings): array
    {
        $price = $line->price->loadMissing(['capacity', 'type']);

        return [
            'service_id' => $line->service->id,
            'service_price_id' => $price->id,
            'ac_brand_id' => $brand?->id,
            'ac_capacity_id' => $price->ac_capacity_id,
            'ac_type_id' => $price->ac_type_id,
            'service_name' => $line->service->name,
            'unit_label' => implode(' · ', array_filter([$brand?->name, $price->capacity->label, $price->type->name])),
            'est_price_min' => $line->priceMin,
            'est_price_max' => $line->priceMax,
            'est_minutes' => $line->minutes,
            'warranty_days' => $line->service->warranty_days ?? $settings->warranty_days,
            'sort_order' => $index + 1,
        ];
    }
}
