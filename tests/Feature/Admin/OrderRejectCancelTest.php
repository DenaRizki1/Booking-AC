<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('menolak order MENUNGGU dengan alasan', function () {
    $order = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();

    $this->post(route('admin.orders.reject', $order), ['reason' => 'Jadwal penuh, silakan pilih hari lain.'])
        ->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::DITOLAK)
        ->and($order->status_reason)->toBe('Jadwal penuh, silakan pilih hari lain.')
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->statusLogs()->latest('id')->first()->changed_by)->toBe($this->admin->id);
});

it('membatalkan order sebelum teknisi berangkat', function (OrderStatus $status) {
    $order = Order::factory()->withStatus($status)->create();

    $this->post(route('admin.orders.cancel', $order), ['reason' => 'Client minta dibatalkan via telepon.'])
        ->assertSessionHas('success', 'Order dibatalkan. Jadwal teknisi & helper dilepas.');

    expect($order->fresh()->status)->toBe(OrderStatus::DIBATALKAN);
})->with([OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI]);

it('admin tetap bisa membatalkan meski sudah lewat batas pembatalan client', function () {
    $order = Order::factory()->at(now()->addHour())->withStatus(OrderStatus::DIKONFIRMASI)->create();

    $this->post(route('admin.orders.cancel', $order), ['reason' => 'Teknisi berhalangan mendadak.'])->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(OrderStatus::DIBATALKAN);
});

it('tidak bisa membatalkan order yang sudah berjalan', function (OrderStatus $status) {
    $order = Order::factory()->withStatus($status)->create();

    $this->post(route('admin.orders.cancel', $order), ['reason' => 'Coba batalkan saja.'])
        ->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHas('error');

    expect($order->fresh()->status)->toBe($status);
})->with([OrderStatus::DALAM_PERJALANAN, OrderStatus::DIKERJAKAN, OrderStatus::SELESAI, OrderStatus::LUNAS]);

it('hanya order MENUNGGU yang bisa ditolak', function () {
    $order = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create();

    $this->post(route('admin.orders.reject', $order), ['reason' => 'Di luar jangkauan.'])->assertSessionHas('error');

    expect($order->fresh()->status)->toBe(OrderStatus::DITUGASKAN);
});

it('mewajibkan alasan', function (string $route) {
    $order = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();

    $this->from(route('admin.orders.show', $order))
        ->post(route($route, $order), ['reason' => ' '])
        ->assertSessionHasErrorsIn('reason', ['reason' => 'Alasan wajib diisi.']);

    expect($order->fresh()->status)->toBe(OrderStatus::MENUNGGU);
})->with(['admin.orders.reject', 'admin.orders.cancel']);

it('menampilkan tombol sesuai status', function () {
    $waiting = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();
    $confirmed = Order::factory()->withStatus(OrderStatus::DIKONFIRMASI)->create();
    $working = Order::factory()->withStatus(OrderStatus::DIKERJAKAN)->create();

    $this->get(route('admin.orders.show', $waiting))->assertSee('Tolak order')->assertSee('Batalkan');
    $this->get(route('admin.orders.show', $confirmed))->assertDontSee('Tolak order')->assertSee('Batalkan order');
    $this->get(route('admin.orders.show', $working))->assertDontSee('Tolak order')->assertDontSee('Batalkan order');
});

it('membuka kembali modal dengan pesan error', function () {
    $order = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();

    $this->from(route('admin.orders.show', $order))
        ->post(route('admin.orders.reject', $order), ['reason' => 'ok', '_modal' => 'reject']);

    $this->get(route('admin.orders.show', $order))->assertSee('Alasan minimal 5 karakter.');
});

it('alasan penolakan tampil di halaman client', function () {
    $order = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();
    $this->post(route('admin.orders.reject', $order), ['reason' => 'Lokasi di luar area layanan kami.']);

    $this->actingAs($order->client)->get(route('client.orders.show', $order))->assertSee('Lokasi di luar area layanan kami.');
});
