<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 07:30'));
    $this->technician = Technician::factory()->create();
    $this->technician->user->update(['name' => 'Rudi Setiawan']);
    $this->order = Order::factory()->at('2026-10-01 10:00', 60)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $this->technician->id]);
    $this->actingAs($this->technician->user);
});

it('menampilkan tombol terima & tolak untuk tugas baru', function () {
    $this->get(route('teknisi.tasks'))->assertSee('Terima tugas')->assertSee('Tolak tugas?');
    $this->get(route('teknisi.tasks.show', $this->order))->assertSee('Terima tugas');
});

it('menerima tugas menjadi DIKONFIRMASI', function () {
    $this->post(route('teknisi.tasks.accept', $this->order))
        ->assertRedirect(route('teknisi.tasks.show', $this->order))
        ->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::DIKONFIRMASI)
        ->and($order->confirmed_at)->not->toBeNull()
        ->and($order->statusLogs()->latest('id')->first()->changed_by)->toBe($this->technician->user_id);
});

it('menolak tugas: kembali MENUNGGU dan teknisi dikosongkan', function () {
    $this->post(route('teknisi.tasks.reject', $this->order), ['reason' => 'Kendaraan mogok'])
        ->assertRedirect(route('teknisi.tasks'))
        ->assertSessionHas('success', 'Tugas ditolak. Admin akan menugaskan teknisi lain.');

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::MENUNGGU)
        ->and($order->technician_id)->toBeNull()
        ->and($order->statusLogs()->latest('id')->first()->note)->toBe('Ditolak teknisi: Kendaraan mogok');

    // Admin melihat peringatan di detail order.
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.orders.show', $order))
        ->assertSee('Rudi Setiawan</b> melepas tugas ini', false)
        ->assertSee('Ditolak teknisi: Kendaraan mogok');
});

it('mewajibkan alasan saat menolak', function () {
    $this->from(route('teknisi.tasks'))
        ->post(route('teknisi.tasks.reject', $this->order), ['reason' => '', 'order_id' => $this->order->id])
        ->assertSessionHasErrorsIn('reject', ['reason' => 'Alasan menolak wajib diisi.']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::DITUGASKAN);
    $this->get(route('teknisi.tasks'))->assertSee('Alasan menolak wajib diisi.');
});

it('tidak bisa menerima tugas teknisi lain', function () {
    $other = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create();

    $this->post(route('teknisi.tasks.accept', $other))->assertForbidden();
    $this->post(route('teknisi.tasks.reject', $other), ['reason' => 'Bukan tugas saya'])->assertForbidden();

    expect($other->fresh()->status)->toBe(OrderStatus::DITUGASKAN);
});

it('tidak bisa menerima tugas yang sudah dikonfirmasi', function () {
    $this->order->update(['status' => OrderStatus::DIKONFIRMASI]);

    $this->from(route('teknisi.tasks'))->post(route('teknisi.tasks.accept', $this->order))->assertSessionHas('error');
});
