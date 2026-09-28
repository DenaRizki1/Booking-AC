<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tukar posisi satu baris dengan tetangganya lalu tulis ulang sort_order (1, 2, 3, …).
 * Dipakai untuk tabel master kecil (merk, PK, tipe, layanan).
 */
class SortOrder
{
    /**
     * @param  Builder<*>  $ordered  query yang sudah terurut sesuai tampilan
     * @param  'up'|'down'  $direction
     */
    public static function move(Builder $ordered, int $id, string $direction): void
    {
        DB::transaction(function () use ($ordered, $id, $direction) {
            $ids = (clone $ordered)->lockForUpdate()->pluck($ordered->getModel()->getKeyName())->all();
            $position = array_search($id, $ids, true);
            abort_if($position === false, 404);

            $target = $direction === 'up' ? $position - 1 : $position + 1;
            if (! isset($ids[$target])) {
                return;
            }

            [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];

            foreach ($ids as $index => $recordId) {
                $ordered->getModel()->newQuery()->whereKey($recordId)->update(['sort_order' => $index + 1]);
            }
        });
    }
}
