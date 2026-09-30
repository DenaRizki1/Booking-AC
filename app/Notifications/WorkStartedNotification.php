<?php

namespace App\Notifications;

use App\Models\User;

/** Client: teknisi tiba dan mulai bekerja. */
class WorkStartedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Pekerjaan dimulai';
    }

    public function message(User $notifiable): string
    {
        return "{$this->technicianName()} sudah tiba dan mulai mengerjakan {$this->order->code}.";
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
