<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;

/** Admin: teknisi menolak tugas → order kembali MENUNGGU. */
class TaskRejectedNotification extends OrderNotification
{
    public function __construct(Order $order, ?string $note = null, public ?string $rejectedBy = null)
    {
        parent::__construct($order, $note);
    }

    public function title(): string
    {
        return 'Teknisi menolak tugas';
    }

    public function message(User $notifiable): string
    {
        return ($this->rejectedBy ?? 'Teknisi')." menolak {$this->order->code} ({$this->schedule()}). Tugaskan ke teknisi lain.";
    }

    public function icon(): string
    {
        return 'user-x';
    }

    protected function level(): string
    {
        return 'error';
    }
}
