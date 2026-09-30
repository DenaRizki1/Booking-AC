<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\User;

/** Client & teknisi: pengingat H-1 jadwal (email saja, dari scheduler). */
class ScheduleReminderNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Pengingat jadwal besok';
    }

    public function message(User $notifiable): string
    {
        if ($notifiable->role === UserRole::TEKNISI) {
            return "Besok Anda bertugas di {$this->order->code} ({$this->order->client->name}) pada {$this->schedule()}. Alamat: {$this->order->address_snapshot}.";
        }

        $technician = $this->order->technician ? " oleh {$this->technicianName()}" : '';

        return "Besok ada jadwal servis AC{$technician} untuk {$this->order->code} pada {$this->schedule()}. Pastikan ada yang di lokasi.";
    }

    public function icon(): string
    {
        return 'calendar-clock';
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }
}
