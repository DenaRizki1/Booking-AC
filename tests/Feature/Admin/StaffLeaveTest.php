<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-09-28 09:00'));
    $this->actingAs(User::factory()->admin()->create());
    $this->technician = Technician::factory()->create();
});

it('memecah rentang cuti menjadi satu baris per hari', function () {
    $this->post(route('admin.technicians.leaves.store', $this->technician), [
        'start_date' => '2026-10-01', 'end_date' => '2026-10-03', 'reason' => 'Acara keluarga',
    ])
        ->assertRedirect(route('admin.technicians.edit', $this->technician).'#cuti')
        ->assertSessionHas('success', 'Cuti 3 hari dicatat.')
        ->assertSessionMissing('warning');

    $leaves = $this->technician->leaves()->orderBy('leave_date')->get();
    expect($leaves->map(fn ($leave) => $leave->leave_date->toDateString())->all())->toBe(['2026-10-01', '2026-10-02', '2026-10-03'])
        ->and($leaves->pluck('reason')->unique()->all())->toBe(['Acara keluarga'])
        ->and($leaves->first()->staff_type)->toBe('technician');
});

it('mencatat cuti satu hari bila tanggal selesai kosong', function () {
    $this->post(route('admin.technicians.leaves.store', $this->technician), ['start_date' => '2026-09-28'])
        ->assertSessionHas('success', 'Cuti 1 hari dicatat.');

    expect($this->technician->leaves()->count())->toBe(1);
});

it('melewati tanggal yang sudah tercatat cuti', function () {
    StaffLeave::factory()->for($this->technician, 'staff')->create(['leave_date' => '2026-10-02']);

    $this->post(route('admin.technicians.leaves.store', $this->technician), ['start_date' => '2026-10-01', 'end_date' => '2026-10-03'])
        ->assertSessionHas('success', 'Cuti dicatat (2 hari baru; tanggal lain sudah tercatat sebelumnya).');

    expect($this->technician->leaves()->count())->toBe(3);
});

it('mencatat cuti helper', function () {
    $helper = Helper::factory()->create();

    $this->post(route('admin.helpers.leaves.store', $helper), ['start_date' => '2026-10-05', 'end_date' => '2026-10-06'])
        ->assertRedirect(route('admin.helpers.edit', $helper).'#cuti');

    expect($helper->leaves()->count())->toBe(2)
        ->and($helper->leaves()->first()->staff_type)->toBe('helper');
});

it('memperingatkan bila cuti bentrok dengan order yang sudah ditugaskan', function () {
    $clash = Order::factory()->at('2026-10-02 10:00')->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $this->technician->id]);
    Order::factory()->at('2026-10-02 13:00')->withStatus(OrderStatus::DIBATALKAN)->create(['technician_id' => $this->technician->id]);
    Order::factory()->at('2026-10-04 10:00')->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $this->technician->id]);

    $this->post(route('admin.technicians.leaves.store', $this->technician), ['start_date' => '2026-10-01', 'end_date' => '2026-10-03'])
        ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'bentrok dengan 1 order')
            && str_contains($message, $clash->code)
            && str_contains($message, 'Jum, 2 Okt'));
});

it('memperingatkan bentrok cuti helper dengan order yang memakai helper tersebut', function () {
    $helper = Helper::factory()->create();
    Order::factory()->withHelper()->at('2026-10-02 10:00')->withStatus(OrderStatus::DIKONFIRMASI)->create(['helper_id' => $helper->id]);

    $this->post(route('admin.helpers.leaves.store', $helper), ['start_date' => '2026-10-02'])
        ->assertSessionHas('warning');
});

it('memvalidasi tanggal cuti', function (array $payload, string $field, string $message) {
    $this->from(route('admin.technicians.edit', $this->technician))
        ->post(route('admin.technicians.leaves.store', $this->technician), $payload)
        ->assertSessionHasErrorsIn('leave', [$field => $message]);
})->with([
    'mulai kosong' => [['start_date' => ''], 'start_date', 'Tanggal mulai cuti wajib diisi.'],
    'masa lalu' => [['start_date' => '2026-09-27'], 'start_date', 'Tanggal mulai cuti tidak boleh sebelum hari ini.'],
    'selesai sebelum mulai' => [['start_date' => '2026-10-05', 'end_date' => '2026-10-04'], 'end_date', 'Tanggal selesai tidak boleh sebelum tanggal mulai.'],
    'rentang terlalu panjang' => [['start_date' => '2026-10-01', 'end_date' => '2026-12-31'], 'end_date', 'Rentang cuti maksimal 62 hari sekali input.'],
    'format salah' => [['start_date' => '01/10/2026'], 'start_date', 'Format tanggal tidak valid.'],
]);

it('menampilkan cuti mendatang di halaman teknisi dan bisa dihapus', function () {
    $past = StaffLeave::factory()->for($this->technician, 'staff')->create(['leave_date' => '2026-09-20']);
    $leave = StaffLeave::factory()->for($this->technician, 'staff')->create(['leave_date' => '2026-10-01', 'reason' => 'Sakit']);

    $this->get(route('admin.technicians.edit', $this->technician))
        ->assertOk()
        ->assertSee('Jadwal cuti mendatang')
        ->assertSee('Kam, 1 Okt 2026')
        ->assertSee('Sakit')
        ->assertDontSee('20 Sep 2026');

    $this->delete(route('admin.leaves.destroy', $leave))
        ->assertRedirect(route('admin.technicians.edit', $this->technician).'#cuti')
        ->assertSessionHas('success', 'Cuti Kam, 1 Okt 2026 dihapus.');

    expect(StaffLeave::query()->pluck('id')->all())->toBe([$past->id]);
});

it('menampilkan kartu cuti di halaman helper', function () {
    $helper = Helper::factory()->create();

    $this->get(route('admin.helpers.edit', $helper))->assertOk()->assertSee('Catat cuti');
});

it('teknisi yang cuti tidak tersedia di jadwal pada hari itu', function () {
    $this->post(route('admin.technicians.leaves.store', $this->technician), ['start_date' => '2026-10-01']);

    $schedule = app(ScheduleService::class);
    $start = CarbonImmutable::parse('2026-10-01 10:00');

    expect($schedule->freeTechnicians($start, $start->addHours(2))->pluck('id'))->not->toContain($this->technician->id)
        ->and($schedule->freeTechnicians($start->addDay(), $start->addDay()->addHours(2))->pluck('id'))->toContain($this->technician->id);
});
