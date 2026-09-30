<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderRejectedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskRejectedNotification;
use App\Notifications\TechnicianAssignedNotification;
use App\Notifications\TechnicianDepartedNotification;
use App\Notifications\WorkStartedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Kirim notifikasi in-app + email sesuai WORKFLOW bagian 14. Pelaku perubahan tidak ikut dinotifikasi.
 */
class SendOrderStatusNotifications implements ShouldQueue
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing(['client', 'technician.user']);
        $actor = $event->actor;

        match (true) {
            $event->from === null => $this->send($this->admins($actor), new NewOrderNotification($order)),

            $event->to === OrderStatus::DITUGASKAN => $this->assigned($order, $actor),

            $event->from === OrderStatus::DITUGASKAN && $event->to === OrderStatus::MENUNGGU && $actor?->role === UserRole::TEKNISI => $this->send($this->admins($actor), new TaskRejectedNotification($order, $this->stripPrefix($event->note), $actor->name)),

            $event->to === OrderStatus::DALAM_PERJALANAN => $this->send($order->client, new TechnicianDepartedNotification($order)),

            $event->to === OrderStatus::DIKERJAKAN => $this->send($order->client, new WorkStartedNotification($order)),

            $event->to === OrderStatus::SELESAI => $this->send($this->admins($actor)->push($order->client), new OrderCompletedNotification($order)),

            $event->to === OrderStatus::DITOLAK => $this->send($order->client, new OrderRejectedNotification($order, $event->note)),

            $event->to === OrderStatus::DIBATALKAN => $this->cancelled($order, $event, $actor),

            default => null,
        };
    }

    private function assigned(Order $order, ?User $actor): void
    {
        if ($order->technician?->user) {
            $this->send($order->technician->user, new TaskAssignedNotification($order));
        }

        $this->send($order->client, new TechnicianAssignedNotification($order));
    }

    private function cancelled(Order $order, OrderStatusChanged $event, ?User $actor): void
    {
        $recipients = new Collection;

        if ($actor?->role !== UserRole::CLIENT) {
            $recipients->push($order->client);
        }
        if ($actor?->role !== UserRole::ADMIN) {
            $recipients = $recipients->merge($this->admins($actor));
        }
        if ($order->technician?->user) {
            $recipients->push($order->technician->user);
        }

        $this->send($recipients, new OrderCancelledNotification($order, $event->note, $actor?->role));
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(?User $except): Collection
    {
        return User::query()
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->get();
    }

    /**
     * @param  User|Collection<int, User>  $recipients
     */
    private function send(User|Collection $recipients, object $notification): void
    {
        $recipients = $recipients instanceof User ? new Collection([$recipients]) : $recipients;
        $active = $recipients->filter(fn (User $user) => $user->is_active && ! $user->trashed())->unique('id');

        if ($active->isNotEmpty()) {
            Notification::send($active, $notification);
        }
    }

    /** "Ditolak teknisi: alasan" → "alasan" */
    private function stripPrefix(?string $note): ?string
    {
        return $note === null ? null : (string) preg_replace('/^Ditolak teknisi:\s*/', '', $note);
    }
}
