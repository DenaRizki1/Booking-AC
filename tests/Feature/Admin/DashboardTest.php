<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Review;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create(['open_time' => '08:00:00', 'close_time' => '18:00:00']);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 07:00'));
    $this->actingAs(User::factory()->admin()->create());
});

it('menampilkan kartu statistik', function () {
    $rudi = Technician::factory()->create();
    $dimas = Technician::factory()->create();
    StaffLeave::factory()->for($dimas, 'staff')->create(['leave_date' => '2026-10-01']);

    Order::factory()->at('2026-10-01 10:00')->withStatus(OrderStatus::MENUNGGU)->create();
    Order::factory()->at('2026-10-03 10:00')->withStatus(OrderStatus::MENUNGGU)->create();
    Order::factory()->at('2026-10-01 13:00', 120)->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $rudi->id]);
    Order::factory()->at('2026-10-01 15:00')->withStatus(OrderStatus::DIBATALKAN)->create();
    Order::factory()->at('2026-09-28 09:00')->withStatus(OrderStatus::SELESAI)->create(['grand_total' => 150000]);
    Order::factory()->at('2026-09-28 13:00')->withStatus(OrderStatus::SELESAI)->create(['grand_total' => 100000]);
    Order::factory()->at('2026-09-27 09:00')->withStatus(OrderStatus::SELESAI)->create(['grand_total' => 0]);
    $past = fn () => Order::factory()->at('2026-09-20 09:00')->withStatus(OrderStatus::LUNAS);
    Review::factory()->create(['rating' => 5, 'order_id' => $past()]);
    Review::factory()->create(['rating' => 4, 'order_id' => $past()]);

    $response = $this->get(route('admin.dashboard'))->assertOk();

    expect($response->viewData('waitingOrders'))->toBe(2)
        ->and($response->viewData('waitingToday'))->toBe(1)
        ->and($response->viewData('todayCount'))->toBe(2)
        ->and($response->viewData('unpaidTotal'))->toBe(250000)
        ->and($response->viewData('unpaidCount'))->toBe(2)
        ->and($response->viewData('onLeaveTechnicians'))->toBe(1)
        ->and($response->viewData('reviewCount'))->toBe(2);

    $response->assertSee('Rp250.000')->assertSee('4,5')->assertSee('1 dijadwalkan hari ini')->assertSee('1 cuti');
});

it('menampilkan order menunggu terdekat dan beban teknisi', function () {
    $rudi = Technician::factory()->create();
    $rudi->user->update(['name' => 'Rudi Setiawan']);
    $later = Order::factory()->at('2026-10-05 09:00')->withStatus(OrderStatus::MENUNGGU)->create();
    $sooner = Order::factory()->at('2026-10-02 09:00')->withStatus(OrderStatus::MENUNGGU)->create();
    Order::factory()->at('2026-10-01 09:00', 150)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $rudi->id]);

    $this->get(route('admin.dashboard'))
        ->assertSeeInOrder([$sooner->code, $later->code])
        ->assertSee('Rudi Setiawan')
        ->assertSee('1 order · 2,5 jam')
        ->assertSee('width: 25%', false);
});

it('menampilkan ulasan terbaru', function () {
    Review::factory()->create(['rating' => 3, 'comment' => 'Datang sedikit terlambat.']);

    $this->get(route('admin.dashboard'))->assertSee('Datang sedikit terlambat.')->assertSee('3 dari 5 bintang');
});

it('menampilkan keadaan kosong', function () {
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Semua order sudah di-assign')->assertSee('Belum ada ulasan.');
});
