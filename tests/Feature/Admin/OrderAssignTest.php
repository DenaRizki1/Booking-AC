<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use App\Services\Assignments\StaffCandidate;
use App\Services\OrderAssignmentService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CompanySettings::factory()->create(['open_time' => '08:00:00', 'close_time' => '18:00:00', 'travel_buffer_minutes' => 30]);
    $this->travelTo(CarbonImmutable::parse('2026-09-29 07:00'));
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->rudi = Technician::factory()->create();
    $this->rudi->user->update(['name' => 'Rudi Setiawan']);
    $this->order = Order::factory()->at('2026-10-01 10:00', 120)->withStatus(OrderStatus::MENUNGGU)->create();
});

function busyOrder(array $attributes, string $start = '2026-10-01 11:00'): Order
{
    return Order::factory()->at($start, 60)->withStatus(OrderStatus::DIKONFIRMASI)->create($attributes);
}

it('menampilkan detail order dengan panel tugaskan tim', function () {
    $this->get(route('admin.orders.show', $this->order))
        ->assertOk()
        ->assertSee($this->order->code)
        ->assertSee('Client &amp; lokasi', false)
        ->assertSee('Unit &amp; layanan', false)
        ->assertSee('Tugaskan tim')
        ->assertSee('Rudi Setiawan')
        ->assertSee('Riwayat status');
});

it('menandai kandidat Free, Bentrok, dan Cuti', function () {
    $busy = Technician::factory()->create();
    $clash = busyOrder(['technician_id' => $busy->id]);
    $away = Technician::factory()->create();
    StaffLeave::factory()->for($away, 'staff')->create(['leave_date' => '2026-10-01']);
    Technician::factory()->inactive()->create();

    $candidates = app(OrderAssignmentService::class)->candidates($this->order)['technicians']->keyBy(fn ($c) => $c->staff->id);

    expect($candidates)->toHaveCount(3)
        ->and($candidates[$this->rudi->id]->status)->toBe(StaffCandidate::FREE)
        ->and($candidates[$busy->id]->status)->toBe(StaffCandidate::CONFLICT)
        ->and($candidates[$busy->id]->conflicts->pluck('id')->all())->toBe([$clash->id])
        ->and($candidates[$away->id]->status)->toBe(StaffCandidate::LEAVE);

    $this->get(route('admin.orders.show', $this->order))->assertSee('Bentrok '.$clash->code)->assertSee('Cuti hari ini');
});

it('order yang berakhir tepat saat order ini mulai tidak dianggap bentrok', function () {
    // 08:00 + 90 menit + buffer 30 = terkunci s/d 10:00.
    Order::factory()->at('2026-10-01 08:00', 90)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $this->rudi->id]);

    $candidate = app(OrderAssignmentService::class)->candidates($this->order)['technicians']->first();

    expect($candidate->status)->toBe(StaffCandidate::FREE)->and($candidate->dayOrders)->toHaveCount(1);
});

it('menyarankan teknisi order asal untuk order garansi', function () {
    $original = Technician::factory()->create();
    $parent = Order::factory()->withStatus(OrderStatus::LUNAS)->create(['technician_id' => $original->id]);
    $claim = Order::factory()->warranty($parent)->at('2026-10-01 13:00', 60)->withStatus(OrderStatus::MENUNGGU)->create();

    $first = app(OrderAssignmentService::class)->candidates($claim)['technicians']->first();

    expect($first->staff->id)->toBe($original->id)->and($first->suggested)->toBeTrue();
    $this->get(route('admin.orders.show', $claim))->assertSee('Teknisi order asal');
});

it('menugaskan teknisi: status DITUGASKAN dan dicatat di log', function () {
    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id])
        ->assertRedirect(route('admin.orders.show', $this->order))
        ->assertSessionHas('success', 'Order ditugaskan ke Rudi Setiawan. Menunggu konfirmasi teknisi.');

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::DITUGASKAN)
        ->and($order->technician_id)->toBe($this->rudi->id)
        ->and($order->assigned_at)->not->toBeNull()
        ->and($order->statusLogs()->latest('id')->first()->note)->toBe('Ditugaskan ke Rudi Setiawan')
        ->and($order->statusLogs()->latest('id')->first()->changed_by)->toBe($this->admin->id);
});

it('menolak assign ke teknisi yang bentrok', function () {
    busyOrder(['technician_id' => $this->rudi->id]);

    $this->from(route('admin.orders.show', $this->order))
        ->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id])
        ->assertRedirect(route('admin.orders.show', $this->order))
        ->assertSessionHasErrors(['technician_id' => 'Rudi Setiawan tidak tersedia di jadwal ini (bentrok, cuti, atau nonaktif). Pilih teknisi lain.']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::MENUNGGU);
});

it('menolak assign ke teknisi yang cuti atau nonaktif', function () {
    StaffLeave::factory()->for($this->rudi, 'staff')->create(['leave_date' => '2026-10-01']);
    $inactive = Technician::factory()->inactive()->create();

    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id])->assertSessionHasErrors('technician_id');
    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $inactive->id])->assertSessionHasErrors('technician_id');

    expect($this->order->fresh()->technician_id)->toBeNull();
});

it('mewajibkan helper untuk order yang butuh helper', function () {
    $order = Order::factory()->withHelper()->at('2026-10-01 13:00', 60)->withStatus(OrderStatus::MENUNGGU)->create(['helper_id' => null]);

    $this->put(route('admin.orders.assign', $order), ['technician_id' => $this->rudi->id])
        ->assertSessionHasErrors(['helper_id' => 'Order ini wajib didampingi helper. Pilih helper.']);

    $helper = Helper::factory()->create(['name' => 'Agus']);
    $this->put(route('admin.orders.assign', $order), ['technician_id' => $this->rudi->id, 'helper_id' => $helper->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Order ditugaskan ke Rudi Setiawan + Agus. Menunggu konfirmasi teknisi.');

    expect($order->fresh()->helper_id)->toBe($helper->id);
});

it('menolak helper yang bentrok', function () {
    $helper = Helper::factory()->create();
    busyOrder(['helper_id' => $helper->id, 'requires_helper' => true]);

    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id, 'helper_id' => $helper->id])
        ->assertSessionHasErrors('helper_id');
});

it('mengganti teknisi mengembalikan status ke DITUGASKAN dan dicatat', function () {
    $this->order->update(['technician_id' => $this->rudi->id, 'status' => OrderStatus::DIKONFIRMASI]);
    $bima = Technician::factory()->create();
    $bima->user->update(['name' => 'Bima']);

    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $bima->id])->assertSessionHasNoErrors();

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::DITUGASKAN)
        ->and($order->technician_id)->toBe($bima->id)
        ->and($order->statusLogs()->latest('id')->first()->note)->toBe('Tim diganti: Rudi Setiawan → Bima');
});

it('mengganti helper saja tidak mengubah status', function () {
    $helper = Helper::factory()->create(['name' => 'Agus']);
    $this->order->update(['technician_id' => $this->rudi->id, 'status' => OrderStatus::DIKONFIRMASI]);

    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id, 'helper_id' => $helper->id])
        ->assertSessionHas('success', 'Helper diganti menjadi Agus.');

    expect($this->order->fresh()->status)->toBe(OrderStatus::DIKONFIRMASI)
        ->and($this->order->fresh()->helper_id)->toBe($helper->id);
});

it('teknisi yang sama tidak dianggap bentrok dengan order ini sendiri', function () {
    $this->order->update(['technician_id' => $this->rudi->id, 'status' => OrderStatus::DITUGASKAN]);

    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id])
        ->assertSessionHas('success', 'Tidak ada perubahan tim.');
});

it('tidak bisa assign order yang sudah berjalan atau selesai', function () {
    $order = Order::factory()->withStatus(OrderStatus::DIKERJAKAN)->create(['technician_id' => $this->rudi->id]);

    $this->put(route('admin.orders.assign', $order), ['technician_id' => $this->rudi->id])
        ->assertSessionHasErrors(['technician_id' => 'Order berstatus Dikerjakan tidak bisa di-assign lagi.']);

    $this->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Tugaskan tim')->assertSee('Tim');
});

it('memvalidasi input assign', function () {
    $this->put(route('admin.orders.assign', $this->order), [])->assertSessionHasErrors(['technician_id' => 'Pilih teknisi yang akan ditugaskan.']);
    $this->put(route('admin.orders.assign', $this->order), ['technician_id' => 99999])->assertSessionHasErrors('technician_id');
});

it('hanya admin yang bisa assign', function () {
    $this->actingAs($this->rudi->user)
        ->put(route('admin.orders.assign', $this->order), ['technician_id' => $this->rudi->id])
        ->assertForbidden();
});

it('memperingatkan bila jadwal order sudah lewat', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 08:00'));

    $this->get(route('admin.orders.show', $this->order))->assertSee('Jadwal order ini sudah lewat.');
});
