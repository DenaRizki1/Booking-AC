<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-09-29 08:00'));
    $this->actingAs(User::factory()->admin()->create());
});

it('menampilkan tab status dengan jumlahnya dan default tab menunggu', function () {
    $waiting = Order::factory()->count(2)->withStatus(OrderStatus::MENUNGGU)->create();
    $assigned = Order::factory()->withStatus(OrderStatus::DIKONFIRMASI)->create();
    Order::factory()->withStatus(OrderStatus::LUNAS)->create();
    Order::factory()->withStatus(OrderStatus::DIBATALKAN)->create();

    $response = $this->get(route('admin.orders.index'))->assertOk()
        ->assertSee($waiting[0]->code)
        ->assertSee($waiting[1]->code)
        ->assertDontSee($assigned->code);

    expect($response->viewData('tabCounts')->all())->toBe([
        'menunggu' => 2, 'dijadwalkan' => 1, 'berjalan' => 0, 'belum-lunas' => 0, 'lunas' => 1, 'batal' => 1, 'semua' => 5,
    ]);

    $this->get(route('admin.orders.index', ['tab' => 'dijadwalkan']))->assertSee($assigned->code)->assertDontSee($waiting[0]->code);
    $this->get(route('admin.orders.index', ['tab' => 'semua']))->assertSee($assigned->code)->assertSee($waiting[0]->code);
});

it('mengurutkan order aktif dari jadwal terdekat', function () {
    $later = Order::factory()->at('2026-10-02 09:00')->withStatus(OrderStatus::MENUNGGU)->create();
    $sooner = Order::factory()->at('2026-09-30 09:00')->withStatus(OrderStatus::MENUNGGU)->create();

    $this->get(route('admin.orders.index'))->assertSeeInOrder([$sooner->code, $later->code]);
});

it('mencari berdasarkan kode atau nama client', function () {
    $client = User::factory()->create(['name' => 'Hendra Wijaya']);
    $match = Order::factory()->for($client, 'client')->withStatus(OrderStatus::MENUNGGU)->create();
    $other = Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();

    $this->get(route('admin.orders.index', ['q' => 'hendra']))->assertSee($match->code)->assertDontSee($other->code);
    $this->get(route('admin.orders.index', ['q' => $other->code]))->assertSee($other->code)->assertDontSee($match->code);
});

it('memfilter tanggal jadwal, jenis, dan teknisi', function () {
    $technician = Technician::factory()->create();
    $a = Order::factory()->at('2026-10-01 09:00')->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $technician->id]);
    $b = Order::factory()->at('2026-10-05 09:00')->withStatus(OrderStatus::DITUGASKAN)->create();
    $warranty = Order::factory()->warranty(Order::factory()->withStatus(OrderStatus::LUNAS)->create())->at('2026-10-01 13:00')->withStatus(OrderStatus::MENUNGGU)->create();

    $this->get(route('admin.orders.index', ['tab' => 'semua', 'from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertSee($a->code)->assertSee($warranty->code)->assertDontSee($b->code);

    $this->get(route('admin.orders.index', ['tab' => 'semua', 'type' => OrderType::GARANSI->value]))
        ->assertSee($warranty->code)->assertDontSee($a->code);

    $this->get(route('admin.orders.index', ['tab' => 'semua', 'technician' => $technician->id]))
        ->assertSee($a->code)->assertDontSee($b->code);

    $this->get(route('admin.orders.index', ['tab' => 'semua', 'technician' => 'none']))
        ->assertSee($warranty->code)->assertDontSee($a->code);
});

it('jumlah per tab mengikuti filter', function () {
    Order::factory()->warranty(Order::factory()->withStatus(OrderStatus::LUNAS)->create())->withStatus(OrderStatus::MENUNGGU)->create();
    Order::factory()->withStatus(OrderStatus::MENUNGGU)->create();

    $counts = $this->get(route('admin.orders.index', ['type' => 'GARANSI']))->viewData('tabCounts');

    expect($counts['menunggu'])->toBe(1)->and($counts['semua'])->toBe(1);
});

it('menolak filter yang tidak valid', function () {
    $this->get(route('admin.orders.index', ['tab' => 'aneh']))->assertSessionHasErrors('tab');
});

it('membagi halaman per 20 order', function () {
    Order::factory()->count(21)->withStatus(OrderStatus::MENUNGGU)->create();

    expect($this->get(route('admin.orders.index'))->viewData('orders')->count())->toBe(20);
});

it('mengaktifkan menu Order di sidebar', function () {
    $this->get(route('admin.orders.index'))->assertSee('href="'.route('admin.orders.index').'"', false);
});
