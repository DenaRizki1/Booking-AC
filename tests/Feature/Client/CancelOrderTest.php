<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create(['cancel_limit_hours' => 3, 'phone' => '081234567890']);
    $this->client = User::factory()->create();
    // Jadwal Selasa 6 Okt 2026 09:00.
    $this->order = Order::factory()->for($this->client, 'client')->at('2026-10-06 09:00', 60)->withStatus(OrderStatus::DITUGASKAN)->create();
});

it('menampilkan tombol batal bila ≥ 3 jam sebelum jadwal', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 06:00'));

    $this->actingAs($this->client)->get("/order/{$this->order->code}")
        ->assertSee('Batalkan order')
        ->assertSee('s/d Sel, 6 Okt, 06:00')
        ->assertDontSee('Batas pembatalan mandiri');
});

it('mengganti tombol batal dengan kontak admin bila sudah lewat batas', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00'));

    $this->actingAs($this->client)->get("/order/{$this->order->code}")
        ->assertDontSee('Batalkan order')
        ->assertSee('Batas pembatalan mandiri (3 jam sebelum jadwal) sudah lewat')
        ->assertSee('https://wa.me/6281234567890')
        ->assertSee('tel:081234567890');
});

it('tidak menawarkan batal untuk order yang sudah berjalan', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 07:00'));
    $order = Order::factory()->for($this->client, 'client')->at('2026-10-06 09:00')->withStatus(OrderStatus::DALAM_PERJALANAN)->create();

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertDontSee('Batalkan order')
        ->assertSee('Ada kendala? Hubungi admin');
});

it('membatalkan order dengan alasan', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 20:00'));

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/batal", ['reason' => 'Ada acara mendadak'])
        ->assertRedirect("/order/{$this->order->code}")
        ->assertSessionHas('success', 'Order dibatalkan.');

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::DIBATALKAN)
        ->and($order->status_reason)->toBe('Ada acara mendadak')
        ->and($order->statusLogs()->latest('id')->first()->changed_by)->toBe($this->client->id);

    $this->actingAs($this->client)->get("/order/{$order->code}")->assertSee('Alasan: Ada acara mendadak');
});

it('mewajibkan alasan pembatalan', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 20:00'));

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/batal", ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Alasan pembatalan wajib diisi.']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::DITUGASKAN);
});

it('menolak pembatalan 2 jam sebelum jadwal', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00'));

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/batal", ['reason' => 'Mendadak'])
        ->assertSessionHas('error', 'Pembatalan hanya bisa paling lambat 3 jam sebelum jadwal. Silakan hubungi admin.');

    expect($this->order->fresh()->status)->toBe(OrderStatus::DITUGASKAN);
});

it('melarang client lain membatalkan', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 20:00'));

    $this->actingAs(User::factory()->create())
        ->post("/order/{$this->order->code}/batal", ['reason' => 'Iseng'])
        ->assertForbidden();

    expect($this->order->fresh()->status)->toBe(OrderStatus::DITUGASKAN);
});
