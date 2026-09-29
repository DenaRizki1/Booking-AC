<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Technician;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 07:30'));
    $this->technician = Technician::factory()->create(['review_count' => 3, 'avg_rating' => 4.9]);
    $this->technician->user->update(['name' => 'Rudi Setiawan']);
    $this->actingAs($this->technician->user);
});

function taskFor(Technician $technician, string $start, OrderStatus $status, array $attributes = []): Order
{
    return Order::factory()->at($start, 60)->withStatus($status)->create(['technician_id' => $technician->id] + $attributes);
}

it('menampilkan tugas hari ini urut jam beserta ringkasan', function () {
    $later = taskFor($this->technician, '2026-10-01 14:00', OrderStatus::DITUGASKAN);
    $sooner = taskFor($this->technician, '2026-10-01 09:00', OrderStatus::DIKONFIRMASI);
    $done = taskFor($this->technician, '2026-10-01 08:00', OrderStatus::SELESAI);
    $tomorrow = taskFor($this->technician, '2026-10-02 09:00', OrderStatus::DIKONFIRMASI);
    $otherTechnician = taskFor(Technician::factory()->create(), '2026-10-01 10:00', OrderStatus::DIKONFIRMASI);

    $response = $this->get(route('teknisi.tasks'))->assertOk()
        ->assertSee('Halo, Rudi')
        ->assertSee('Bertugas')
        ->assertSee('4,9')
        ->assertSeeInOrder([$done->code, $sooner->code, $later->code])
        ->assertSee('Perlu konfirmasi')
        ->assertDontSee($tomorrow->code)
        ->assertDontSee($otherTechnician->code);

    expect($response->viewData('todayCount'))->toBe(3)->and($response->viewData('todayDone'))->toBe(1);
});

it('menampilkan tugas aktif dari hari sebelumnya di tab hari ini', function () {
    $overdue = taskFor($this->technician, '2026-09-30 15:00', OrderStatus::DIKERJAKAN);

    $this->get(route('teknisi.tasks'))->assertSee($overdue->code)->assertSee('Terlewat');
});

it('memisahkan tab mendatang dan riwayat', function () {
    $upcoming = taskFor($this->technician, '2026-10-03 09:00', OrderStatus::DITUGASKAN);
    $cancelled = taskFor($this->technician, '2026-10-04 09:00', OrderStatus::DIBATALKAN);
    $history = taskFor($this->technician, '2026-09-20 09:00', OrderStatus::LUNAS);

    $this->get(route('teknisi.tasks', ['tab' => 'mendatang']))
        ->assertSee($upcoming->code)->assertDontSee($cancelled->code)->assertDontSee($history->code);

    $this->get(route('teknisi.tasks', ['tab' => 'riwayat']))
        ->assertSee($history->code)->assertDontSee($upcoming->code);
});

it('menampilkan keadaan kosong', function () {
    $this->get(route('teknisi.tasks'))->assertSee('Tidak ada tugas hari ini');
});

it('menampilkan detail tugas dengan client, alamat, unit, dan helper', function () {
    $helper = Helper::factory()->create(['name' => 'Agus']);
    $order = taskFor($this->technician, '2026-10-01 10:00', OrderStatus::DIKONFIRMASI, ['helper_id' => $helper->id, 'customer_note' => 'Unit di lantai 2']);
    $item = OrderItem::factory()->for($order)->create();

    $this->get(route('teknisi.tasks.show', $order))
        ->assertOk()
        ->assertSee($order->client->name)
        ->assertSee($order->address_snapshot)
        ->assertSee($item->unit_label)
        ->assertSee('Helper: <b class="text-ink">Agus</b>', false)
        ->assertSee('Unit di lantai 2')
        ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination='.$order->lat.','.$order->lng, false);
});

it('teknisi tidak bisa membuka tugas teknisi lain', function () {
    $order = taskFor(Technician::factory()->create(), '2026-10-01 10:00', OrderStatus::DIKONFIRMASI);

    $this->get(route('teknisi.tasks.show', $order))->assertForbidden();
});

it('menampilkan halaman akun dengan tombol keluar', function () {
    $this->get(route('teknisi.account'))->assertOk()->assertSee('Rudi Setiawan')->assertSee('Keluar');
});
