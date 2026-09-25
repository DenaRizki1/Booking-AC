<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PhotoKind;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00')); // Senin
    CompanySettings::factory()->create();
    Technician::factory()->create();

    $this->client = User::factory()->create();
    $this->order = Order::factory()->for($this->client, 'client')->at('2026-10-01 09:00', 120)->withStatus(OrderStatus::LUNAS)->create();
    $this->unitA = OrderItem::factory()->for($this->order)->create(['unit_label' => 'Daikin · 1 PK · Standar', 'est_minutes' => 60, 'warranty_days' => 7, 'warranty_until' => '2026-10-08', 'sort_order' => 1]);
    $this->unitB = OrderItem::factory()->for($this->order)->create(['unit_label' => 'LG · 1½ PK · Inverter', 'est_minutes' => 90, 'warranty_until' => '2026-10-08', 'sort_order' => 2]);
});

function claimInput(array $overrides = []): array
{
    return array_merge([
        'items' => [test()->unitA->id],
        'complaint' => 'AC kembali menetes air setelah dicuci.',
        'date' => '2026-10-06',
        'time' => '10:00',
    ], $overrides);
}

it('menampilkan tombol klaim di detail order yang masih bergaransi', function () {
    $this->actingAs($this->client)->get("/order/{$this->order->code}")
        ->assertSee('Garansi aktif')
        ->assertSee('Sampai Kam, 8 Okt 2026')
        ->assertSee('Klaim Garansi');
});

it('menampilkan garansi berakhir tanpa tombol klaim', function () {
    OrderItem::query()->update(['warranty_until' => '2026-10-04']);

    $this->actingAs($this->client)->get("/order/{$this->order->code}")
        ->assertSee('Garansi berakhir')
        ->assertDontSee('Klaim Garansi');
    $this->actingAs($this->client)->get("/order/{$this->order->code}/garansi")
        ->assertRedirect("/order/{$this->order->code}")
        ->assertSessionHas('error', 'Tidak ada unit yang masih bisa diklaim garansinya.');
});

it('menampilkan form klaim dengan unit bergaransi', function () {
    $this->actingAs($this->client)->get("/order/{$this->order->code}/garansi")
        ->assertOk()
        ->assertSee('Daikin · 1 PK · Standar')
        ->assertSee('LG · 1½ PK · Inverter')
        ->assertSee('Kirim klaim garansi');
});

it('memberi jadwal sesuai durasi unit yang dipilih', function () {
    $dates = $this->actingAs($this->client)
        ->getJson("/order/{$this->order->code}/garansi/jadwal?".http_build_query(['items' => [$this->unitA->id, $this->unitB->id]]))
        ->assertOk()
        ->json('dates');

    $tuesday = collect($dates)->firstWhere('date', '2026-10-06');
    expect($dates)->toHaveCount(14)
        ->and(collect($tuesday['slots'])->firstWhere('time', '09:00')['end'])->toBe('11:30'); // 60 + 90 menit
});

it('membuat order GARANSI Rp0 yang terhubung ke order & unit asal', function () {
    Storage::fake('public');

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/garansi", claimInput(['photo' => UploadedFile::fake()->image('keluhan.jpg', 800, 600)]))
        ->assertSessionHasNoErrors();

    $claim = Order::where('order_type', OrderType::GARANSI)->sole();
    expect($claim)
        ->parent_order_id->toBe($this->order->id)
        ->user_id->toBe($this->client->id)
        ->customer_address_id->toBe($this->order->customer_address_id)
        ->address_snapshot->toBe($this->order->address_snapshot)
        ->status->toBe(OrderStatus::MENUNGGU)
        ->est_price_min->toBe(0)
        ->est_price_max->toBe(0)
        ->transport_fee->toBe(0)
        ->total_est_minutes->toBe(60)
        ->customer_note->toBe('AC kembali menetes air setelah dicuci.')
        ->and($claim->scheduled_start->toDateTimeString())->toBe('2026-10-06 10:00:00');

    $item = $claim->items->sole();
    expect($item->claimed_item_id)->toBe($this->unitA->id)
        ->and($item->unit_label)->toBe('Daikin · 1 PK · Standar')
        ->and($item->est_price_max)->toBe(0);

    $photo = $claim->photos->sole();
    expect($photo->kind)->toBe(PhotoKind::KELUHAN)
        ->and($photo->order_item_id)->toBeNull();
    Storage::disk('public')->assertExists($photo->path);

    expect($claim->statusLogs()->sole()->note)->toBe("Klaim garansi dari {$this->order->code}");
});

it('tidak bisa mengklaim unit yang klaimnya masih berjalan', function () {
    $this->actingAs($this->client)->post("/order/{$this->order->code}/garansi", claimInput());

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/garansi", claimInput(['time' => '13:00']))
        ->assertSessionHasErrors(['items' => 'Pilih unit yang masih dalam masa garansi dan belum diklaim.']);

    // Unit lain masih bisa diklaim; detail order menunjukkan klaim yang sedang diproses setelah semua diklaim.
    $this->actingAs($this->client)->post("/order/{$this->order->code}/garansi", claimInput(['items' => [$this->unitB->id], 'time' => '13:00']))->assertSessionHasNoErrors();
    $this->actingAs($this->client)->get("/order/{$this->order->code}")->assertSee('diproses')->assertDontSee('Klaim Garansi');
});

it('bisa mengklaim lagi setelah klaim sebelumnya selesai', function () {
    $previous = Order::factory()->warranty($this->order)->withStatus(OrderStatus::SELESAI)->create();
    OrderItem::factory()->for($previous)->create(['claimed_item_id' => $this->unitA->id]);

    $this->actingAs($this->client)->post("/order/{$this->order->code}/garansi", claimInput())->assertSessionHasNoErrors();
});

it('menolak klaim untuk order yang belum selesai atau milik client lain', function () {
    $running = Order::factory()->for($this->client, 'client')->withStatus(OrderStatus::DIKERJAKAN)->create();
    $runningItem = OrderItem::factory()->for($running)->create(['warranty_until' => '2026-10-08']);

    $this->actingAs($this->client)->post("/order/{$running->code}/garansi", claimInput(['items' => [$runningItem->id]]))->assertSessionHasErrors('items');
    $this->actingAs(User::factory()->create())->post("/order/{$this->order->code}/garansi", claimInput())->assertForbidden();
});

it('menolak unit dari order lain', function () {
    $otherItem = OrderItem::factory()->for(Order::factory()->for($this->client, 'client')->withStatus(OrderStatus::LUNAS))->create(['warranty_until' => '2026-10-08']);

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/garansi", claimInput(['items' => [$otherItem->id]]))
        ->assertSessionHasErrors('items');
});

it('menolak slot yang tidak tersedia', function () {
    Technician::all()->each(fn (Technician $technician) => Order::factory()->at('2026-10-06 10:00', 120)
        ->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $technician->id]));

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/garansi", claimInput())
        ->assertSessionHasErrors(['time' => 'Jam 10:00 pada 6 Oktober tidak tersedia. Silakan pilih jam lain.']);
});

it('memvalidasi keluhan & foto', function () {
    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/garansi", claimInput(['complaint' => '', 'items' => [], 'photo' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors([
            'complaint' => 'Jelaskan keluhan Anda.',
            'items' => 'Pilih minimal 1 unit yang bermasalah.',
            'photo' => 'File harus berupa foto.',
        ]);

    $this->actingAs($this->client)
        ->post("/order/{$this->order->code}/garansi", claimInput(['photo' => UploadedFile::fake()->image('besar.jpg')->size(6000)]))
        ->assertSessionHasErrors(['photo' => 'Ukuran foto maksimal 5 MB.']);
});

it('mengarahkan kartu garansi di beranda ke halaman klaim', function () {
    $this->actingAs($this->client)->get('/beranda')
        ->assertSee('Garansi aktif')
        ->assertSee("/order/{$this->order->code}/garansi");
});
