<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Notifications\ScheduleReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 18:00'));
    $this->technician = Technician::factory()->create();
});

it('mengirim email pengingat ke client & teknisi untuk order besok', function () {
    $assigned = Order::factory()->at('2026-10-06 09:00')->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $this->technician->id]);
    $waiting = Order::factory()->at('2026-10-06 13:00')->withStatus(OrderStatus::MENUNGGU)->create();
    $cancelled = Order::factory()->at('2026-10-06 15:00')->withStatus(OrderStatus::DIBATALKAN)->create();
    $later = Order::factory()->at('2026-10-07 09:00')->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $this->technician->id]);

    $this->artisan('orders:send-reminders')->expectsOutputToContain('3 email')->assertSuccessful();

    Notification::assertSentTo($assigned->client, ScheduleReminderNotification::class, fn ($n) => $n->order->is($assigned));
    Notification::assertSentTo($this->technician->user, ScheduleReminderNotification::class, fn ($n) => $n->order->is($assigned));
    Notification::assertSentTo($waiting->client, ScheduleReminderNotification::class);
    Notification::assertNotSentTo($cancelled->client, ScheduleReminderNotification::class);
    Notification::assertNotSentTo($later->client, ScheduleReminderNotification::class);
    Notification::assertCount(3);
});

it('tidak mengirim ulang bila dijalankan dua kali di hari yang sama', function () {
    Order::factory()->at('2026-10-06 09:00')->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $this->technician->id]);

    $this->artisan('orders:send-reminders')->assertSuccessful();
    $this->artisan('orders:send-reminders')->expectsOutputToContain('0 email')->assertSuccessful();

    Notification::assertCount(2);
});

it('pengingat hanya lewat email dengan pesan sesuai peran', function () {
    $order = Order::factory()->at('2026-10-06 09:00')->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $this->technician->id]);
    $notification = new ScheduleReminderNotification($order);

    expect($notification->via($order->client))->toBe(['mail'])
        ->and($notification->message($this->technician->user))->toContain('Besok Anda bertugas')
        ->and($notification->message($order->client))->toContain('Besok ada jadwal servis AC');
});

it('dijadwalkan tiap hari jam 18:00', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'orders:send-reminders'));

    expect($event?->expression)->toBe('0 18 * * *');
});
