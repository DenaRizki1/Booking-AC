<?php

namespace App\Services;

use App\Exceptions\UnavailableUnitsException;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Services\Estimates\EstimateLine;
use App\Services\Estimates\OrderEstimate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Estimasi harga & durasi order dari daftar unit AC (matriks Layanan × PK × Tipe).
 */
class OrderEstimator
{
    /**
     * @param  array<int, array{service_id: int|string, ac_capacity_id: int|string, ac_type_id: int|string}>  $units  (kunci diabaikan; indeks error mengikuti urutan)
     *
     * @throws UnavailableUnitsException jika ada unit tanpa harga aktif
     */
    public function estimate(array $units): OrderEstimate
    {
        if ($units === []) {
            throw new InvalidArgumentException('Order minimal berisi 1 unit AC.');
        }

        $prices = $this->activePrices($units);
        $lines = [];
        $errors = [];

        foreach (array_values($units) as $index => $unit) {
            $price = $prices->get($this->key($unit['service_id'], $unit['ac_capacity_id'], $unit['ac_type_id']));

            if ($price === null) {
                $errors[$index] = $this->unavailableMessage($index, $unit);

                continue;
            }

            $lines[] = EstimateLine::fromPrice($price);
        }

        if ($errors !== []) {
            throw new UnavailableUnitsException($errors);
        }

        return new OrderEstimate($lines);
    }

    /**
     * Harga aktif (layanan, PK & tipe juga aktif) untuk kombinasi yang diminta, di-key "service-pk-tipe".
     *
     * @param  array<int, array{service_id: int|string, ac_capacity_id: int|string, ac_type_id: int|string}>  $units
     * @return Collection<string, ServicePrice>
     */
    private function activePrices(array $units): Collection
    {
        return ServicePrice::query()
            ->where('is_active', true)
            ->whereIn('service_id', array_column($units, 'service_id'))
            ->whereIn('ac_capacity_id', array_column($units, 'ac_capacity_id'))
            ->whereIn('ac_type_id', array_column($units, 'ac_type_id'))
            ->whereHas('service', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('capacity', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('type', fn (Builder $query) => $query->where('is_active', true))
            ->with(['service', 'capacity', 'type'])
            ->get()
            ->keyBy(fn (ServicePrice $price) => $this->key($price->service_id, $price->ac_capacity_id, $price->ac_type_id));
    }

    private function key(int|string $serviceId, int|string $capacityId, int|string $typeId): string
    {
        return "{$serviceId}-{$capacityId}-{$typeId}";
    }

    /**
     * @param  array{service_id: int|string, ac_capacity_id: int|string, ac_type_id: int|string}  $unit
     */
    private function unavailableMessage(int $index, array $unit): string
    {
        $service = Service::find($unit['service_id']);
        $capacity = AcCapacity::find($unit['ac_capacity_id']);
        $type = AcType::find($unit['ac_type_id']);
        $number = $index + 1;

        if ($service === null || $capacity === null || $type === null) {
            return "Unit {$number}: data layanan, PK, atau tipe AC tidak valid.";
        }

        return "Unit {$number}: layanan {$service->name} belum tersedia untuk {$capacity->label} · {$type->name}.";
    }
}
