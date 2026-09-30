<?php

namespace App\Notifications;

use App\Models\User;

/** Client: teknisi sedang menuju lokasi. */
class TechnicianDepartedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Teknisi dalam perjalanan';
    }

    public function message(User $notifiable): string
    {
        return "{$this->technicianName()} sedang menuju lokasi Anda untuk {$this->order->code}.";
    }

    public function icon(): string
    {
        return 'navigation';
    }
}
