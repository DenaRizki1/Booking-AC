<?php

namespace App\Notifications;

use App\Models\User;

/** Client: order ditolak admin (alasan di $note). */
class OrderRejectedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Order ditolak';
    }

    public function message(User $notifiable): string
    {
        return "Mohon maaf, order {$this->order->code} ({$this->schedule()}) tidak dapat kami proses.";
    }

    public function icon(): string
    {
        return 'circle-x';
    }

    protected function level(): string
    {
        return 'error';
    }
}
