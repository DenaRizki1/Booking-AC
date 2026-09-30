<?php

namespace App\Notifications;

use App\Models\User;

/** Client: teknisi sudah ditugaskan ke ordernya. */
class TechnicianAssignedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Teknisi ditugaskan';
    }

    public function message(User $notifiable): string
    {
        return "{$this->technicianName()} akan menangani {$this->order->code} pada {$this->schedule()}.";
    }

    public function icon(): string
    {
        return 'user-check';
    }
}
