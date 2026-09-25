<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderStatusChanged;
use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AcMasterSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00')); // Senin
    CompanySettings::factory()->create();
    $this->seed([AcMasterSeeder::class, ServiceSeeder::class]);
    Technician::factory()->create();

    $this->client = User::factory()->create();
    $this->address = CustomerAddress::factory()->for($this->client)->default()->create([
        'label' => 'Rumah', 'address' => 'Jl. Kemang Raya No. 12', 'landmark' => 'Pagar hitam', 'distance_km' => 8.4,
    ]);
});

function orderUnit(string $service, string $pk, string $type, string $brand): array
{
    return [
        'ac_brand_id' => AcBrand::where('name', $brand)->value('id'),
        'ac_capacity_id' => AcCapacity::where('pk_value', $pk)->value('id'),
        'ac_type_id' => AcType::where('name', $type)->value('id'),
        'service_id' => Service::where('slug', $service)->value('id'),
    ];
}

/**
 * Isi langkah 1 wizard dengan contoh 2 unit di mockup.
 */
function completeUnitStep(array $units = []): void
{
    $test = test();
    $units = $units ?: [orderUnit('cuci-ac', '1.00', 'Standar', 'Daikin'), orderUnit('cuci-ac', '1.50', 'Inverter', 'LG')];

    $test->actingAs($test->client)
        ->post('/order/baru', ['customer_address_id' => $test->address->id, 'units' => $units])
        ->assertRedirect('/order/baru/jadwal');
}

it('kembali ke langkah unit bila langkah 1 belum selesai', function () {
    $this->actingAs($this->client)->get('/order/baru/jadwal')->assertRedirect('/order/baru');
    $this->actingAs($this->client)->post('/order', ['date' => '2026-10-06', 'time' => '09:00'])->assertRedirect('/order/baru');
});

it('menampilkan 14 hari, slot jam, dan ringkasan sesuai mockup', function () {
    completeUnitStep();

    $response = $this->actingAs($this->client)->get('/order/baru/jadwal')
        ->assertOk()
        ->assertSee('Pilih tanggal')
        ->assertSee('Transport (3,4 km di luar radius gratis)')
        ->assertSee('Rp12.000')
        ->assertSee('Rp197.000 – Rp232.000')
        ->assertSeeInOrder(['Cuci AC', 'Daikin · 1 PK · Standar', 'Rp75rb – 90rb', 'Cuci AC', 'LG · 1½ PK · Inverter', 'Rp110rb – 130rb'])
        ->assertSee('Pembatalan gratis hingga 3 jam sebelum jadwal');

    $dates = collect($response->viewData('dates'));
    $sunday = $dates->firstWhere('date', '2026-10-11');
    $tuesday = $dates->firstWhere('date', '2026-10-06');

    expect($dates)->toHaveCount(14)
        ->and($dates->first()['date'])->toBe('2026-10-05')
        ->and($sunday['working'])->toBeFalse()
        ->and(collect($tuesday['slots'])->pluck('time')->all())->toBe(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'])
        ->and(collect($tuesday['slots'])->firstWhere('time', '11:00')['end'])->toBe('13:30');
});

it('menandai tanggal penuh bila semua teknisi sibuk sepanjang hari', function () {
    Order::factory()->at('2026-10-06 08:00', 540)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => Technician::first()->id]);
    completeUnitStep();

    $tuesday = collect($this->actingAs($this->client)->get('/order/baru/jadwal')->viewData('dates'))->firstWhere('date', '2026-10-06');

    expect($tuesday['full'])->toBeTrue()
        ->and(collect($tuesday['slots'])->contains('available', true))->toBeFalse();
});

it('menyimpan order dengan snapshot harga, alamat, jadwal, dan log MENUNGGU', function () {
    Event::fake([OrderStatusChanged::class]);
    completeUnitStep();

    $response = $this->actingAs($this->client)->post('/order', [
        'date' => '2026-10-06', 'time' => '11:00', 'customer_note' => 'AC kamar depan kurang dingin.',
    ]);

    $order = Order::sole();
    $response->assertRedirect("/order/{$order->code}")
        ->assertSessionHas('success', "Order {$order->code} berhasil dibuat. Admin akan segera menugaskan teknisi.");

    expect($order)
        ->code->toBe('ORD-2610-0001')
        ->order_type->toBe(OrderType::REGULER)
        ->status->toBe(OrderStatus::MENUNGGU)
        ->user_id->toBe($this->client->id)
        ->customer_address_id->toBe($this->address->id)
        ->address_snapshot->toBe('Jl. Kemang Raya No. 12 (Patokan: Pagar hitam)')
        ->distance_km->toBe('8.40')
        ->technician_id->toBeNull()
        ->requires_helper->toBeFalse()
        ->total_est_minutes->toBe(150)
        ->est_price_min->toBe(185000)
        ->est_price_max->toBe(220000)
        ->transport_fee->toBe(12000)
        ->customer_note->toBe('AC kamar depan kurang dingin.')
        ->and($order->scheduled_start->toDateTimeString())->toBe('2026-10-06 11:00:00')
        ->and($order->scheduled_end->toDateTimeString())->toBe('2026-10-06 13:30:00')
        ->and($order->blocked_until->toDateTimeString())->toBe('2026-10-06 14:00:00');

    expect($order->items->map->only(['service_name', 'unit_label', 'est_price_min', 'est_price_max', 'est_minutes', 'warranty_days', 'sort_order'])->all())->toBe([
        ['service_name' => 'Cuci AC', 'unit_label' => 'Daikin · 1 PK · Standar', 'est_price_min' => 75000, 'est_price_max' => 90000, 'est_minutes' => 60, 'warranty_days' => 7, 'sort_order' => 1],
        ['service_name' => 'Cuci AC', 'unit_label' => 'LG · 1½ PK · Inverter', 'est_price_min' => 110000, 'est_price_max' => 130000, 'est_minutes' => 90, 'warranty_days' => 7, 'sort_order' => 2],
    ]);

    $log = $order->statusLogs()->sole();
    expect([$log->from_status, $log->to_status, $log->changed_by])->toBe([null, OrderStatus::MENUNGGU, $this->client->id])
        ->and(session('order_draft'))->toBeNull();

    Event::assertDispatched(OrderStatusChanged::class, fn ($event) => $event->order->is($order) && $event->from === null);
});

it('tidak terpengaruh perubahan harga setelah order dibuat (snapshot)', function () {
    completeUnitStep();
    $this->actingAs($this->client)->post('/order', ['date' => '2026-10-06', 'time' => '09:00']);

    ServicePrice::query()->update(['price_min' => 999000, 'price_max' => 999000]);
    Service::where('slug', 'cuci-ac')->update(['name' => 'Cuci AC Premium']);

    $order = Order::sole()->fresh('items');
    expect($order->est_price_max)->toBe(220000)
        ->and($order->items->first()->service_name)->toBe('Cuci AC');
});

it('menolak slot yang baru saja terisi dengan pesan jelas', function () {
    completeUnitStep();

    // Client lain lebih dulu memesan jam yang sama; hanya ada 1 teknisi.
    Order::factory()->at('2026-10-06 11:00', 60)->create();

    $this->actingAs($this->client)
        ->from('/order/baru/jadwal')
        ->post('/order', ['date' => '2026-10-06', 'time' => '11:00'])
        ->assertRedirect('/order/baru/jadwal')
        ->assertSessionHasErrors(['time' => 'Jam 11:00 pada 6 Oktober baru saja terisi atau tidak tersedia. Silakan pilih jam lain.']);

    expect(Order::where('user_id', $this->client->id)->count())->toBe(0)
        ->and(session('order_draft.ready'))->toBeTrue();
});

it('menolak jam di luar jam operasional atau yang sudah lewat', function (string $date, string $time) {
    completeUnitStep();

    $this->actingAs($this->client)->post('/order', ['date' => $date, 'time' => $time])->assertSessionHasErrors('time');
})->with([
    'lewat jam tutup' => ['2026-10-06', '16:00'],   // 150 menit + buffer → 19:00
    'sebelum buka' => ['2026-10-06', '07:00'],
    'hari libur' => ['2026-10-11', '09:00'],
    'sudah lewat' => ['2026-10-05', '06:00'],
    'bukan kelipatan slot' => ['2026-10-06', '09:15'],
]);

it('memvalidasi tanggal dalam 14 hari ke depan', function () {
    completeUnitStep();

    $this->actingAs($this->client)->post('/order', ['date' => '2026-10-19', 'time' => '09:00'])
        ->assertSessionHasErrors(['date' => 'Tanggal harus dalam 14 hari ke depan.']);
    $this->actingAs($this->client)->post('/order', [])
        ->assertSessionHasErrors(['date' => 'Pilih tanggal.', 'time' => 'Pilih jam mulai.']);
});

it('mewajibkan helper free untuk layanan wajib helper', function () {
    completeUnitStep([orderUnit('instalasi', '1.00', 'Standar', 'Sharp')]);

    $this->actingAs($this->client)->post('/order', ['date' => '2026-10-06', 'time' => '09:00'])->assertSessionHasErrors('time');

    Helper::factory()->create();
    $this->actingAs($this->client)->post('/order', ['date' => '2026-10-06', 'time' => '09:00'])->assertSessionHasNoErrors();

    expect(Order::sole()->requires_helper)->toBeTrue();
});

it('menolak bila alamat dihapus sebelum konfirmasi', function () {
    completeUnitStep();
    $this->address->delete();

    $this->actingAs($this->client)->post('/order', ['date' => '2026-10-06', 'time' => '09:00'])
        ->assertRedirect('/order/baru')
        ->assertSessionHasErrors(['customer_address_id' => 'Alamat tidak ditemukan. Pilih alamat lain.']);
    expect(Order::count())->toBe(0);
});

it('hanya pemilik yang bisa membuka detail order', function () {
    $order = Order::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->client)->get("/order/{$order->code}")->assertOk()->assertSee($order->code);
    $this->actingAs(User::factory()->create())->get("/order/{$order->code}")->assertForbidden();
});
