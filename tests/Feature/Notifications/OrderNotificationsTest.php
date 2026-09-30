<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
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
use App\Services\OrderStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00'));

    $this->admin = User::factory()->admin()->create();
    $this->otherAdmin = User::factory()->admin()->create();
    User::factory()->admin()->inactive()->create(); // admin nonaktif tidak menerima
    $this->technician = Technician::factory()->create();
    $this->technician->user->update(['name' => 'Rudi Setiawan']);
    $this->client = User::factory()->create();
    $this->statuses = app(OrderStatusService::class);
});

function orderAt(OrderStatus $status, array $attributes = []): Order
{
    $test = test();

    return Order::factory()->for($test->client, 'client')->at('2026-10-06 10:00', 60)->withStatus($status)->create(array_merge([
        'technician_id' => $status === OrderStatus::MENUNGGU ? null : $test->technician->id,
    ], $attributes));
}

it('order baru → semua admin aktif', function () {
    $order = orderAt(OrderStatus::MENUNGGU);
    $this->statuses->recordCreated($order, $this->client);

    Notification::assertSentTo([$this->admin, $this->otherAdmin], NewOrderNotification::class, fn ($n) => $n->order->is($order));
    Notification::assertCount(2);
});

it('teknisi ditugaskan → teknisi & client', function () {
    $order = orderAt(OrderStatus::MENUNGGU);
    $this->statuses->transition($order, OrderStatus::DITUGASKAN, $this->admin, attributes: ['technician_id' => $this->technician->id]);

    Notification::assertSentTo($this->technician->user, TaskAssignedNotification::class);
    Notification::assertSentTo($this->client, TechnicianAssignedNotification::class, function ($notification) {
        return str_contains($notification->message($this->client), 'Rudi Setiawan');
    });
    Notification::assertNotSentTo($this->admin, TaskAssignedNotification::class);
});

it('teknisi menolak tugas → admin (beserta alasan)', function () {
    $order = orderAt(OrderStatus::DITUGASKAN);
    $this->statuses->transition($order, OrderStatus::MENUNGGU, $this->technician->user, 'Ditolak teknisi: Kendaraan mogok');

    Notification::assertSentTo([$this->admin, $this->otherAdmin], TaskRejectedNotification::class, fn ($n) => $n->note === 'Kendaraan mogok'
        && str_contains($n->message($this->admin), 'Rudi Setiawan menolak'));
    Notification::assertNotSentTo($this->client, TaskRejectedNotification::class);
});

it('teknisi berangkat & mulai → client', function () {
    $order = orderAt(OrderStatus::DIKONFIRMASI);

    $this->statuses->transition($order, OrderStatus::DALAM_PERJALANAN, $this->technician->user);
    Notification::assertSentTo($this->client, TechnicianDepartedNotification::class);

    $this->statuses->transition($order, OrderStatus::DIKERJAKAN, $this->technician->user);
    Notification::assertSentTo($this->client, WorkStartedNotification::class);

    Notification::assertNotSentTo($this->admin, TechnicianDepartedNotification::class);
});

it('laporan selesai → client & admin', function () {
    $order = orderAt(OrderStatus::DIKERJAKAN);
    $this->statuses->transition($order, OrderStatus::SELESAI, $this->technician->user, attributes: ['final_price' => 150000, 'grand_total' => 162000]);

    Notification::assertSentTo([$this->client, $this->admin, $this->otherAdmin], OrderCompletedNotification::class);
    Notification::assertNotSentTo($this->technician->user, OrderCompletedNotification::class);
});

it('order ditolak admin → client', function () {
    $order = orderAt(OrderStatus::MENUNGGU);
    $this->statuses->transition($order, OrderStatus::DITOLAK, $this->admin, 'Di luar jangkauan');

    Notification::assertSentTo($this->client, OrderRejectedNotification::class, fn ($n) => $n->note === 'Di luar jangkauan');
    Notification::assertNotSentTo($this->admin, OrderRejectedNotification::class);
});

it('dibatalkan client → admin & teknisi', function () {
    $order = orderAt(OrderStatus::DIKONFIRMASI);
    $this->statuses->transition($order, OrderStatus::DIBATALKAN, $this->client, 'Ada acara mendadak');

    Notification::assertSentTo([$this->admin, $this->otherAdmin, $this->technician->user], OrderCancelledNotification::class);
    Notification::assertNotSentTo($this->client, OrderCancelledNotification::class);
});

it('dibatalkan admin → client & teknisi, admin lain tidak', function () {
    $order = orderAt(OrderStatus::DITUGASKAN);
    $this->statuses->transition($order, OrderStatus::DIBATALKAN, $this->admin, 'Teknisi berhalangan');

    Notification::assertSentTo([$this->client, $this->technician->user], OrderCancelledNotification::class);
    Notification::assertNotSentTo([$this->admin, $this->otherAdmin], OrderCancelledNotification::class);
});

it('menyimpan data in-app dan email dengan tautan sesuai peran', function () {
    $order = orderAt(OrderStatus::DITUGASKAN);
    $notification = new TaskAssignedNotification($order);

    expect($notification->via($this->technician->user))->toBe(['database', 'mail'])
        ->and($notification->toArray($this->technician->user))->toMatchArray([
            'order_id' => $order->id,
            'code' => $order->code,
            'title' => 'Tugas baru',
            'url' => route('teknisi.tasks.show', $order),
        ])
        ->and($notification->urlFor($this->client))->toBe(route('client.orders.show', $order))
        ->and($notification->urlFor($this->admin))->toBe(route('admin.orders.show', $order));

    $mail = $notification->toMail($this->technician->user);
    expect($mail->subject)->toBe("Tugas baru · {$order->code}")
        ->and($mail->greeting)->toBe('Halo, Rudi!')
        ->and($mail->actionText)->toBe('Buka tugas');
});

it('notifikasi diantrekan', function () {
    expect(new TaskAssignedNotification(orderAt(OrderStatus::DITUGASKAN)))->toBeInstanceOf(ShouldQueue::class);
});
