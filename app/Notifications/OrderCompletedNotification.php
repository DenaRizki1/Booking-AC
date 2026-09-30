<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Format;

/** Client & admin: laporan teknisi terkirim, order SELESAI. */
class OrderCompletedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Pekerjaan selesai';
    }

    public function message(User $notifiable): string
    {
        $billed = (int) $this->order->grand_total > 0;
        $total = Format::rupiah((int) $this->order->grand_total);

        if ($notifiable->role === UserRole::ADMIN) {
            return $billed
                ? "Laporan {$this->order->code} terkirim. Total tagihan {$total} — konfirmasi pembayaran setelah diterima."
                : "Laporan {$this->order->code} terkirim. Order tanpa tagihan (garansi) sudah selesai.";
        }

        return $billed
            ? "Pekerjaan {$this->order->code} selesai. Total tagihan {$total}. Lihat laporan & beri ulasan untuk teknisi."
            : "Pekerjaan garansi {$this->order->code} selesai tanpa biaya. Lihat laporan teknisi.";
    }

    public function icon(): string
    {
        return 'circle-check';
    }

    protected function level(): string
    {
        return 'success';
    }
}
