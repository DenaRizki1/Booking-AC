<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\ScheduleReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pengingat H-1: email ke client & teknisi untuk order yang dijadwalkan besok.
 * Aman dijalankan ulang di hari yang sama (tiap order hanya diingatkan sekali).
 */
class SendScheduleReminders extends Command
{
    protected $signature = 'orders:send-reminders';

    protected $description = 'Kirim email pengingat jadwal besok ke client & teknisi (dijadwalkan tiap hari 18:00)';

    public function handle(): int
    {
        $tomorrow = CarbonImmutable::tomorrow();
        $sent = 0;

        Order::query()
            ->whereIn('status', [OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI])
            ->where('scheduled_start', '>=', $tomorrow)
            ->where('scheduled_start', '<', $tomorrow->addDay())
            ->with(['client', 'technician.user'])
            ->orderBy('scheduled_start')
            ->each(function (Order $order) use ($tomorrow, &$sent) {
                if (! Cache::add("order-reminder:{$order->id}:{$tomorrow->toDateString()}", true, now()->addDays(2))) {
                    return;
                }

                $recipients = collect([$order->client, $order->technician?->user])
                    ->filter(fn ($user) => $user !== null && $user->is_active);

                foreach ($recipients as $user) {
                    $user->notify(new ScheduleReminderNotification($order));
                    $sent++;
                }
            });

        $this->info("Pengingat terkirim: {$sent} email untuk jadwal {$tomorrow->translatedFormat('j F Y')}.");

        return self::SUCCESS;
    }
}
