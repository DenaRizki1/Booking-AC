<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Technician;
use App\Models\User;
use App\Support\Format;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 08:00'));
    CompanySettings::factory()->create();
    $this->client = User::factory()->create();
});

function clientOrder(OrderStatus $status, array $attributes = []): Order
{
    $order = Order::factory()->for(test()->client, 'client')->withStatus($status)->create($attributes);
    OrderItem::factory()->for($order)->create(['service_name' => 'Cuci AC', 'unit_label' => 'Daikin · 1 PK · Standar']);

    return $order;
}

it('memisahkan order per tab Aktif, Selesai, Dibatalkan', function () {
    $active = clientOrder(OrderStatus::DITUGASKAN);
    $done = clientOrder(OrderStatus::LUNAS);
    $cancelled = clientOrder(OrderStatus::DIBATALKAN);
    $foreign = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create();

    $this->actingAs($this->client)->get('/order')
        ->assertOk()
        ->assertSee($active->code)->assertDontSee($done->code)->assertDontSee($cancelled->code)->assertDontSee($foreign->code)
        ->assertSee('1 unit · Cuci AC');

    $this->actingAs($this->client)->get('/order?tab=selesai')
        ->assertSee($done->code)->assertDontSee($active->code)
        ->assertSee(Format::rupiah($done->grand_total));

    $this->actingAs($this->client)->get('/order?tab=dibatalkan')->assertSee($cancelled->code)->assertDontSee($done->code);
});

it('menampilkan pesan kosong per tab', function () {
    $this->actingAs($this->client)->get('/order')->assertSee('Tidak ada order aktif');
    $this->actingAs($this->client)->get('/order?tab=selesai')->assertSee('Belum ada order selesai');
});

it('menampilkan detail: timeline, teknisi & helper, unit, dan estimasi', function () {
    $technician = Technician::factory()->create(['avg_rating' => 4.9, 'review_count' => 3]);
    $technician->user->update(['name' => 'Rudi Setiawan', 'phone' => '081311110001']);
    $order = clientOrder(OrderStatus::DALAM_PERJALANAN, [
        'technician_id' => $technician->id,
        'helper_id' => Helper::factory()->create(['name' => 'Agus'])->id,
        'est_price_min' => 75000, 'est_price_max' => 90000, 'transport_fee' => 12000,
        'customer_note' => 'AC menetes',
    ]);
    OrderStatusLog::factory()->for($order)->create(['to_status' => OrderStatus::MENUNGGU]);

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertOk()
        ->assertSeeInOrder(['Dipesan', 'Ditugaskan', 'Perjalanan', 'Dikerjakan', 'Selesai'])
        ->assertSee('Teknisi dalam perjalanan')
        ->assertSee('Rudi Setiawan')
        ->assertSee('Helper: Agus')
        ->assertSee('★ 4,9')
        ->assertSee('https://wa.me/6281311110001')
        ->assertSee('Daikin · 1 PK · Standar')
        ->assertSee('Rp87.000 – Rp102.000')
        ->assertSee('AC menetes')
        ->assertSee('Riwayat status');
});

it('menunjukkan order menunggu belum punya teknisi', function () {
    $order = clientOrder(OrderStatus::MENUNGGU);

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertSee('Menunggu konfirmasi admin')
        ->assertSee('Admin sedang memilih teknisi');
});

it('menampilkan alasan order ditolak', function () {
    $order = clientOrder(OrderStatus::DITOLAK, ['status_reason' => 'Jadwal penuh']);

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertSee('Order ditolak')
        ->assertSee('Alasan: Jadwal penuh')
        ->assertDontSee('Perjalanan');
});

it('menolak membuka detail order client lain', function () {
    $foreign = Order::factory()->create();

    $this->actingAs($this->client)->get("/order/{$foreign->code}")->assertForbidden();
});
