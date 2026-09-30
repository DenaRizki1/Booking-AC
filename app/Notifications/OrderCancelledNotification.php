<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/** Client / admin / teknisi: order dibatalkan oleh pihak lain (alasan di $note). */
class OrderCancelledNotification extends OrderNotification
{
    public function __construct(Order $order, ?string $note = null, public ?UserRole $cancelledBy = null)
    {
        parent::__construct($order, $note);
    }

    public function title(): string
    {
        return 'Order dibatalkan';
    }

    public function message(User $notifiable): string
    {
        $by = match ($this->cancelledBy) {
            UserRole::CLIENT => ' oleh client',
            UserRole::ADMIN => ' oleh admin',
            default => '',
        };

        $message = "Order {$this->order->code} ({$this->schedule()}) dibatalkan{$by}.";

        return $notifiable->role === UserRole::TEKNISI ? $message.' Jadwal Anda untuk order ini sudah dilepas.' : $message;
    }

    public function icon(): string
    {
        return 'ban';
    }

    protected function level(): string
    {
        return 'error';
    }
}
