<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create(['open_time' => '08:00:00', 'close_time' => '18:00:00', 'travel_buffer_minutes' => 30]);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 11:40'));
    $this->actingAs(User::factory()->admin()->create());
    $this->rudi = Technician::factory()->create();
    $this->rudi->user->update(['name' => 'Rudi Setiawan']);
});

it('menampilkan timeline hari ini dengan blok order dan garis waktu sekarang', function () {
    $helper = Helper::factory()->create(['name' => 'Agus']);
    $order = Order::factory()->at('2026-10-01 10:00', 120)->withStatus(OrderStatus::DALAM_PERJALANAN)
        ->create(['technician_id' => $this->rudi->id, 'helper_id' => $helper->id, 'requires_helper' => true]);
    Order::factory()->at('2026-10-02 10:00', 60)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $this->rudi->id, 'code' => 'ORD-BESOK-0001']);

    $this->get(route('admin.schedule'))
        ->assertOk()
        ->assertSee('Rudi Setiawan')
        ->assertSee('1 order · 2 jam')
        ->assertSee($order->code)
        ->assertSee('Dalam Perjalanan')
        ->assertSee('Ikut Rudi Setiawan')
        ->assertSee('bersama Rudi Setiawan')
        ->assertSee('11:40')
        // 10:00 pada rentang 08:00–18:00 = 20%; kerja 2 jam + buffer 30 menit = 25%.
        ->assertSee('left: 20%; width: 25%', false)
        ->assertDontSee('ORD-BESOK-0001');
});

it('berpindah tanggal lewat parameter', function () {
    Order::factory()->at('2026-10-02 13:00', 60)->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $this->rudi->id, 'code' => 'ORD-BESOK-0001']);

    $this->get(route('admin.schedule', ['tanggal' => '2026-10-02']))
        ->assertOk()
        ->assertSee('ORD-BESOK-0001')
        ->assertSee('Hari ini')
        ->assertSee('tanggal=2026-10-01', false)
        ->assertSee('tanggal=2026-10-03', false)
        ->assertDontSee('11:40</span>', false);
});

it('menandai staf yang cuti', function () {
    StaffLeave::factory()->for($this->rudi, 'staff')->create(['leave_date' => '2026-10-01', 'reason' => 'Sakit']);

    $this->get(route('admin.schedule'))->assertSee('Cuti sepanjang hari · Sakit');
});

it('tidak menampilkan order batal dan menyorot order yang belum di-assign', function () {
    $cancelled = Order::factory()->at('2026-10-01 13:00', 60)->withStatus(OrderStatus::DIBATALKAN)->create(['technician_id' => $this->rudi->id]);
    $waiting = Order::factory()->at('2026-10-01 15:00', 60)->withStatus(OrderStatus::MENUNGGU)->create();

    $this->get(route('admin.schedule'))
        ->assertDontSee($cancelled->code)
        ->assertSee('1 order belum di-assign')
        ->assertSee($waiting->code.' · 15:00');
});

it('menolak tanggal tidak valid', function () {
    $this->get(route('admin.schedule', ['tanggal' => '01-10-2026']))->assertSessionHasErrors('tanggal');
});
