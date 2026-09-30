<?php

namespace App\Notifications;

use App\Enums\OrderType;
use App\Models\User;

/** Admin: order baru / klaim garansi baru menunggu assign. */
class NewOrderNotification extends OrderNotification
{
    public function title(): string
    {
        return $this->order->order_type === OrderType::GARANSI ? 'Klaim garansi baru' : 'Order baru';
    }

    public function message(User $notifiable): string
    {
        return "{$this->order->client->name} membuat {$this->order->code} untuk {$this->schedule()}. Tugaskan teknisi.";
    }

    public function icon(): string
    {
        return $this->order->order_type === OrderType::GARANSI ? 'shield-check' : 'clipboard-plus';
    }
}
