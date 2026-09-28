<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\DB;

/**
 * Simpan matriks harga layanan (PK × Tipe) dalam satu kali upsert.
 */
class PriceMatrixService
{
    /**
     * $cells[ac_capacity_id][ac_type_id] = harga, atau null = sel dikosongkan (baris dihapus).
     *
     * @param  array<int, array<int, array{price_min: int, price_max: int, est_minutes: int, is_active: bool}|null>>  $cells
     * @return array{saved: int, removed: int}
     */
    public function save(Service $service, array $cells): array
    {
        $rows = [];
        $cleared = [];

        foreach ($cells as $capacityId => $types) {
            foreach ($types as $typeId => $cell) {
                if ($cell === null) {
                    $cleared[] = [$capacityId, $typeId];

                    continue;
                }

                $rows[] = [
                    'service_id' => $service->id,
                    'ac_capacity_id' => $capacityId,
                    'ac_type_id' => $typeId,
                    'price_min' => $cell['price_min'],
                    'price_max' => $cell['price_max'],
                    'est_minutes' => $cell['est_minutes'],
                    'is_active' => $cell['is_active'],
                ];
            }
        }

        return DB::transaction(function () use ($service, $rows, $cleared) {
            if ($rows !== []) {
                ServicePrice::query()->upsert(
                    $rows,
                    ['service_id', 'ac_capacity_id', 'ac_type_id'],
                    ['price_min', 'price_max', 'est_minutes', 'is_active'],
                );
            }

            $removed = 0;
            foreach ($cleared as [$capacityId, $typeId]) {
                $removed += $service->prices()
                    ->where('ac_capacity_id', $capacityId)
                    ->where('ac_type_id', $typeId)
                    ->delete();
            }

            return ['saved' => count($rows), 'removed' => $removed];
        });
    }
}
