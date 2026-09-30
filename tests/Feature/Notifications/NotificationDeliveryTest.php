<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;

it('menyimpan notifikasi in-app dan mengirim email saat teknisi ditugaskan', function () {
    CompanySettings::factory()->create();
    Event::fake([MessageSent::class]);
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create();
    $order = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();

    $this->actingAs($admin)->put(route('admin.orders.assign', $order), ['technician_id' => $technician->id])->assertSessionHasNoErrors();

    $inApp = $technician->user->notifications()->sole();
    expect($inApp->data['title'])->toBe('Tugas baru')
        ->and($inApp->data['url'])->toBe(route('teknisi.tasks.show', $order))
        ->and($inApp->read_at)->toBeNull()
        ->and($order->client->notifications()->sole()->data['title'])->toBe('Teknisi ditugaskan');

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => collect($event->message->getTo())->contains(fn ($address) => $address->getAddress() === $technician->user->email));
});
