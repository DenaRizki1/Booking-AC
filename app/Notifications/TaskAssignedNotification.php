<?php

namespace App\Notifications;

use App\Models\User;

/** Teknisi: mendapat tugas baru (perlu diterima / ditolak). */
class TaskAssignedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Tugas baru';
    }

    public function message(User $notifiable): string
    {
        return "Anda ditugaskan ke {$this->order->code} ({$this->order->client->name}) pada {$this->schedule()}. Terima atau tolak tugas ini.";
    }

    public function icon(): string
    {
        return 'clipboard-list';
    }
}
