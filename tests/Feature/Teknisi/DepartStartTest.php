<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));
    $this->technician = Technician::factory()->create();
    $this->order = Order::factory()->at('2026-10-01 10:00', 60)->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $this->technician->id]);
    $this->actingAs($this->technician->user);
});

it('menampilkan tombol navigasi & berangkat untuk tugas yang dikonfirmasi', function () {
    $this->get(route('teknisi.tasks.show', $this->order))
        ->assertSee('Berangkat')
        ->assertSee('Navigasi')
        ->assertSee('google.com/maps/dir/?api=1&amp;destination='.$this->order->lat.','.$this->order->lng, false);
});

it('berangkat lalu tiba & mulai kerja', function () {
    $this->post(route('teknisi.tasks.depart', $this->order))->assertRedirect(route('teknisi.tasks.show', $this->order));
    expect($this->order->fresh()->status)->toBe(OrderStatus::DALAM_PERJALANAN)
        ->and($this->order->fresh()->departed_at)->not->toBeNull();

    $this->get(route('teknisi.tasks.show', $this->order))->assertSee('Tiba &amp; mulai kerja', false);

    $this->post(route('teknisi.tasks.start', $this->order))->assertSessionHas('success');
    expect($this->order->fresh()->status)->toBe(OrderStatus::DIKERJAKAN)
        ->and($this->order->fresh()->started_at)->not->toBeNull();
});

it('tidak bisa berangkat sebelum hari jadwal', function () {
    $tomorrow = Order::factory()->at('2026-10-02 10:00', 60)->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $this->technician->id]);

    $this->from(route('teknisi.tasks.show', $tomorrow))
        ->post(route('teknisi.tasks.depart', $tomorrow))
        ->assertSessionHas('error', 'Tombol Berangkat baru bisa dipakai pada hari jadwal.');

    expect($tomorrow->fresh()->status)->toBe(OrderStatus::DIKONFIRMASI);
});

it('tidak bisa melompati urutan status', function () {
    $this->from(route('teknisi.tasks.show', $this->order))
        ->post(route('teknisi.tasks.start', $this->order))
        ->assertSessionHas('error');

    expect($this->order->fresh()->status)->toBe(OrderStatus::DIKONFIRMASI);
});

it('tidak bisa mengubah status tugas teknisi lain', function () {
    $other = Order::factory()->at('2026-10-01 13:00')->withStatus(OrderStatus::DIKONFIRMASI)->create();

    $this->post(route('teknisi.tasks.depart', $other))->assertForbidden();
});
