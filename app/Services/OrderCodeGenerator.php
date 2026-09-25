<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Kode order ORD-YYMM-NNNN; nomor urut direset tiap bulan.
 *
 * Aman dari balapan: nomor dinaikkan dengan satu statement atomik
 * (INSERT … ON DUPLICATE KEY UPDATE) yang mengunci baris periode sampai transaksi selesai.
 */
class OrderCodeGenerator
{
    public function next(?DateTimeInterface $at = null): string
    {
        $period = CarbonImmutable::instance($at ?? now())->format('ym');

        return sprintf('ORD-%s-%04d', $period, $this->nextNumber($period));
    }

    private function nextNumber(string $period): int
    {
        return DB::transaction(function () use ($period) {
            // 1 baris baru = periode pertama kali dipakai; 2 = baris lama dinaikkan.
            $affected = DB::affectingStatement(
                'INSERT INTO order_code_sequences (period, last_number) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
                [$period],
            );

            return $affected === 1 ? 1 : (int) DB::scalar('SELECT LAST_INSERT_ID()');
        });
    }
}
