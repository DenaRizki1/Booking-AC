<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PhotoKind;
use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AcMasterSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Satu skenario utuh lewat HTTP (T-1001):
| client order 2 unit → admin assign → teknisi terima, berangkat, mulai, laporan
| → admin LUNAS → client ulasan → klaim garansi → order garansi selesai tanpa tagihan.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00')); // Senin
    CompanySettings::factory()->create(['warranty_days' => 7]);
    $this->seed([AcMasterSeeder::class, ServiceSeeder::class]);

    $this->admin = User::factory()->admin()->create();
    $this->rudi = Technician::factory()->create();
    $this->rudi->user->update(['name' => 'Rudi Setiawan']);
    $this->helper = Helper::factory()->create(['name' => 'Agus']);

    $this->client = User::factory()->create(['name' => 'Dena Rizki', 'phone' => '081234567890']);
    $this->address = CustomerAddress::factory()->for($this->client)->default()->create(['distance_km' => 8.4]);
});

function e2eUnit(string $service, string $pk, string $type, string $brand): array
{
    return [
        'ac_brand_id' => AcBrand::where('name', $brand)->value('id'),
        'ac_capacity_id' => AcCapacity::where('pk_value', $pk)->value('id'),
        'ac_type_id' => AcType::where('name', $type)->value('id'),
        'service_id' => Service::where('slug', $service)->value('id'),
    ];
}

it('menjalankan alur order dari awal sampai klaim garansi', function () {
    // 1. Client membuat order 2 unit.
    $this->actingAs($this->client)
        ->post('/order/baru', [
            'customer_address_id' => $this->address->id,
            'units' => [e2eUnit('cuci-ac', '1.00', 'Standar', 'Daikin'), e2eUnit('cuci-ac', '1.50', 'Inverter', 'LG')],
        ])
        ->assertRedirect('/order/baru/jadwal');

    $this->post('/order', ['date' => '2026-10-06', 'time' => '09:00', 'customer_note' => 'Unit di kamar atas.'])
        ->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::MENUNGGU)
        ->and($order->items)->toHaveCount(2)
        ->and($order->transport_fee)->toBe(12000);

    $this->get("/order/{$order->code}")->assertOk()->assertSee($order->code);

    // 2. Admin melihat order di tab Menunggu lalu menugaskan Rudi + Agus.
    $this->actingAs($this->admin)->get(route('admin.orders.index'))->assertSee($order->code);
    $this->put(route('admin.orders.assign', $order), ['technician_id' => $this->rudi->id, 'helper_id' => $this->helper->id])
        ->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(OrderStatus::DITUGASKAN);

    // 3. Teknisi menerima tugas, lalu di hari H berangkat dan mulai kerja.
    $this->actingAs($this->rudi->user)->get(route('teknisi.tasks', ['tab' => 'mendatang']))->assertSee($order->code);
    $this->post(route('teknisi.tasks.accept', $order))->assertSessionHas('success');

    $this->travelTo(CarbonImmutable::parse('2026-10-06 08:30'));
    $this->post(route('teknisi.tasks.depart', $order))->assertSessionHas('success');
    $this->travelTo(CarbonImmutable::parse('2026-10-06 09:05'));
    $this->post(route('teknisi.tasks.start', $order))->assertSessionHas('success');
    expect($order->fresh()->status)->toBe(OrderStatus::DIKERJAKAN);

    // 4. Laporan per unit: foto sesudah + harga final (unit 2 di atas estimasi → keterangan).
    [$unitA, $unitB] = $order->items()->get()->all();
    foreach ([$unitA, $unitB] as $unit) {
        $this->post(route('teknisi.reports.photos.store', [$order, $unit]), [
            'kind' => PhotoKind::SESUDAH->value,
            'photos' => [UploadedFile::fake()->image('sesudah.jpg', 2000, 1500)],
        ])->assertSessionHasNoErrors();
    }
    $this->put(route('teknisi.reports.items.update', [$order, $unitA]), ['final_price' => '85.000', 'work_note' => 'Filter dibersihkan'])->assertSessionHasNoErrors();
    $this->put(route('teknisi.reports.items.update', [$order, $unitB]), ['final_price' => '150.000'])->assertSessionHasErrors('price_note');
    $this->put(route('teknisi.reports.items.update', [$order, $unitB]), ['final_price' => '150.000', 'price_note' => 'Ganti kapasitor'])->assertSessionHasNoErrors();

    $this->travelTo(CarbonImmutable::parse('2026-10-06 11:10'));
    $this->post(route('teknisi.reports.submit', $order))->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::SELESAI)
        ->and($order->final_price)->toBe(235000)
        ->and($order->grand_total)->toBe(247000)
        ->and($unitA->fresh()->warranty_until->toDateString())->toBe('2026-10-13');

    // 5. Admin mengonfirmasi pembayaran transfer.
    $this->actingAs($this->admin)
        ->post(route('admin.orders.payment', $order), ['payment_method' => 'TRANSFER', 'payment_proof' => UploadedFile::fake()->image('bukti.jpg')])
        ->assertSessionHas('success');
    expect($order->fresh()->status)->toBe(OrderStatus::LUNAS)
        ->and($order->fresh()->payment_method)->toBe(PaymentMethod::TRANSFER);

    // 6. Client memberi ulasan → rating teknisi diperbarui.
    $this->actingAs($this->client)
        ->post("/order/{$order->code}/ulasan", ['rating' => 5, 'comment' => 'Rapi dan tepat waktu.'])
        ->assertSessionHasNoErrors();
    expect($this->rudi->fresh()->review_count)->toBe(1)
        ->and((float) $this->rudi->fresh()->avg_rating)->toBe(5.0);

    // 7. Dua hari kemudian unit 1 bermasalah → klaim garansi.
    $this->travelTo(CarbonImmutable::parse('2026-10-08 07:00'));
    $this->post("/order/{$order->code}/garansi", [
        'items' => [$unitA->id],
        'complaint' => 'AC menetes lagi.',
        'date' => '2026-10-09',
        'time' => '10:00',
    ])->assertSessionHasNoErrors();

    $claim = Order::query()->where('order_type', OrderType::GARANSI)->sole();
    expect($claim->parent_order_id)->toBe($order->id)
        ->and($claim->status)->toBe(OrderStatus::MENUNGGU)
        ->and($claim->transport_fee)->toBe(0);

    // 8. Admin melihat saran teknisi order asal, menugaskan Rudi; teknisi menyelesaikan tanpa tagihan.
    $this->actingAs($this->admin)->get(route('admin.orders.show', $claim))->assertSee('Teknisi order asal');
    $this->put(route('admin.orders.assign', $claim), ['technician_id' => $this->rudi->id])->assertSessionHasNoErrors();

    $this->actingAs($this->rudi->user)->post(route('teknisi.tasks.accept', $claim));
    $this->travelTo(CarbonImmutable::parse('2026-10-09 09:30'));
    $this->post(route('teknisi.tasks.depart', $claim));
    $this->post(route('teknisi.tasks.start', $claim));

    $claimItem = $claim->items()->sole();
    $this->post(route('teknisi.reports.photos.store', [$claim, $claimItem]), ['kind' => 'SESUDAH', 'photos' => [UploadedFile::fake()->image('ok.jpg')]]);
    $this->put(route('teknisi.reports.items.update', [$claim, $claimItem]), ['final_price' => '0']);
    $this->post(route('teknisi.reports.submit', $claim))->assertSessionHas('success');

    $claim->refresh();
    expect($claim->status)->toBe(OrderStatus::SELESAI)
        ->and($claim->grand_total)->toBe(0);

    // Order garansi Rp0 tidak butuh konfirmasi pembayaran.
    $this->actingAs($this->admin)->get(route('admin.orders.show', $claim))->assertSee('tidak perlu konfirmasi pembayaran');

    // Riwayat status order asal tercatat lengkap.
    expect($order->statusLogs()->pluck('to_status')->map->value->all())->toBe([
        'MENUNGGU', 'DITUGASKAN', 'DIKONFIRMASI', 'DALAM_PERJALANAN', 'DIKERJAKAN', 'SELESAI', 'LUNAS',
    ]);
});
