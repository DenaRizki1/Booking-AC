<?php

use App\Enums\OrderStatus;
use App\Enums\PhotoKind;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 11:00'));
    $this->technician = Technician::factory()->create();
    $this->order = Order::factory()->at('2026-10-01 10:00', 150)->withStatus(OrderStatus::DIKERJAKAN)
        ->create(['technician_id' => $this->technician->id, 'transport_fee' => 12000, 'final_price' => null, 'grand_total' => null]);
    $this->unitA = OrderItem::factory()->for($this->order)->create(['est_price_min' => 75000, 'est_price_max' => 90000, 'warranty_days' => 7, 'sort_order' => 1]);
    $this->unitB = OrderItem::factory()->for($this->order)->create(['est_price_min' => 110000, 'est_price_max' => 130000, 'warranty_days' => 30, 'sort_order' => 2]);
    $this->actingAs($this->technician->user);
});

function afterPhoto(OrderItem $item): OrderPhoto
{
    return OrderPhoto::factory()->create(['order_id' => $item->order_id, 'order_item_id' => $item->id, 'kind' => PhotoKind::SESUDAH]);
}

it('menampilkan form laporan dengan tab per unit', function () {
    $this->get(route('teknisi.reports.edit', $this->order))
        ->assertOk()
        ->assertSee('Unit 1')
        ->assertSee('Unit 2')
        ->assertSee($this->unitA->unit_label)
        ->assertSee('Wajib min. 1')
        ->assertSee('capture="environment"', false);

    $this->get(route('teknisi.reports.edit', ['order' => $this->order, 'unit' => 2]))->assertSee('Simpan Unit 2');
});

it('mengunggah foto dan memperkecilnya maks. 1600px', function () {
    $this->post(route('teknisi.reports.photos.store', [$this->order, $this->unitA]), [
        'kind' => 'SEBELUM',
        'photos' => [UploadedFile::fake()->image('besar.jpg', 4000, 3000)],
    ])->assertRedirect(route('teknisi.reports.edit', ['order' => $this->order, 'unit' => 1]))->assertSessionHas('success');

    $photo = $this->unitA->photos()->sole();
    expect($photo->kind)->toBe(PhotoKind::SEBELUM)->and($photo->uploaded_by)->toBe($this->technician->user_id);
    Storage::disk('public')->assertExists($photo->path);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($photo->path));
    expect($width)->toBe(1600)->and($height)->toBe(1200);
});

it('menolak file yang bukan foto', function () {
    $this->post(route('teknisi.reports.photos.store', [$this->order, $this->unitA]), [
        'kind' => 'SESUDAH',
        'photos' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');

    expect($this->unitA->photos()->count())->toBe(0);
});

it('menghapus foto', function () {
    $this->post(route('teknisi.reports.photos.store', [$this->order, $this->unitA]), ['kind' => 'SESUDAH', 'photos' => [UploadedFile::fake()->image('a.jpg')]]);
    $photo = $this->unitA->photos()->sole();

    $this->delete(route('teknisi.reports.photos.destroy', [$this->order, $photo]))->assertSessionHas('success', 'Foto dihapus.');

    expect(OrderPhoto::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($photo->path);
});

it('menyimpan harga final & catatan unit', function () {
    $this->put(route('teknisi.reports.items.update', [$this->order, $this->unitA]), ['final_price' => '85.000', 'work_note' => 'Filter dibersihkan'])
        ->assertRedirect(route('teknisi.reports.edit', ['order' => $this->order, 'unit' => 2]))
        ->assertSessionHas('success', 'Unit 1 disimpan.');

    expect($this->unitA->fresh()->only(['final_price', 'work_note', 'price_note']))
        ->toBe(['final_price' => 85000, 'work_note' => 'Filter dibersihkan', 'price_note' => null]);
});

it('mewajibkan keterangan bila harga di atas estimasi maksimal', function () {
    $this->put(route('teknisi.reports.items.update', [$this->order, $this->unitB]), ['final_price' => '170.000'])
        ->assertSessionHasErrors(['price_note' => 'Harga di atas estimasi maksimal. Jelaskan tambahan biayanya (terlihat oleh client).']);

    $this->put(route('teknisi.reports.items.update', [$this->order, $this->unitB]), ['final_price' => '170.000', 'price_note' => 'Ganti kapasitor (+Rp40.000)'])
        ->assertSessionHasNoErrors();

    expect($this->unitB->fresh()->final_price)->toBe(170000);
});

it('menolak kirim laporan bila ada unit tanpa foto sesudah', function () {
    $this->unitA->update(['final_price' => 85000]);
    $this->unitB->update(['final_price' => 120000]);
    afterPhoto($this->unitA);

    $this->from(route('teknisi.reports.edit', $this->order))
        ->post(route('teknisi.reports.submit', $this->order))
        ->assertSessionHas('error', 'Lengkapi dulu Unit 2: harga final dan minimal 1 foto sesudah.');

    expect($this->order->fresh()->status)->toBe(OrderStatus::DIKERJAKAN);
});

it('menolak kirim laporan bila harga final belum diisi', function () {
    afterPhoto($this->unitA);
    afterPhoto($this->unitB);
    $this->unitA->update(['final_price' => 85000]);

    $this->post(route('teknisi.reports.submit', $this->order))->assertSessionHas('error');

    expect($this->order->fresh()->status)->toBe(OrderStatus::DIKERJAKAN);
});

it('mengirim laporan: SELESAI, total & garansi terhitung benar', function () {
    afterPhoto($this->unitA);
    afterPhoto($this->unitB);
    $this->unitA->update(['final_price' => 85000]);
    $this->unitB->update(['final_price' => 170000, 'price_note' => 'Ganti kapasitor']);

    $this->post(route('teknisi.reports.submit', $this->order))
        ->assertRedirect(route('teknisi.tasks.show', $this->order))
        ->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::SELESAI)
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->final_price)->toBe(255000)
        ->and($order->grand_total)->toBe(267000)
        ->and($this->unitA->fresh()->warranty_until->toDateString())->toBe('2026-10-08')
        ->and($this->unitB->fresh()->warranty_until->toDateString())->toBe('2026-10-31');
});

it('order garansi dengan harga 0 langsung selesai tanpa tagihan', function () {
    $this->order->update(['transport_fee' => 0]);
    $this->unitB->delete();
    afterPhoto($this->unitA);
    $this->unitA->update(['final_price' => 0]);

    $this->post(route('teknisi.reports.submit', $this->order))->assertSessionHas('success');

    expect($this->order->fresh()->grand_total)->toBe(0);
});

it('laporan hanya bisa diisi saat order dikerjakan', function () {
    $this->order->update(['status' => OrderStatus::DIKONFIRMASI]);

    $this->get(route('teknisi.reports.edit', $this->order))->assertRedirect(route('teknisi.tasks.show', $this->order));
    $this->put(route('teknisi.reports.items.update', [$this->order, $this->unitA]), ['final_price' => '85000'])->assertSessionHas('error');
    $this->post(route('teknisi.reports.photos.store', [$this->order, $this->unitA]), ['kind' => 'SESUDAH', 'photos' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertSessionHas('error');

    expect($this->unitA->fresh()->final_price)->toBeNull()->and(OrderPhoto::query()->count())->toBe(0);
});

it('teknisi lain tidak bisa mengisi laporan', function () {
    $this->actingAs(Technician::factory()->create()->user);

    $this->get(route('teknisi.reports.edit', $this->order))->assertForbidden();
    $this->post(route('teknisi.reports.submit', $this->order))->assertForbidden();
});

it('unit dari order lain ditolak', function () {
    $foreign = OrderItem::factory()->create();

    $this->put(route('teknisi.reports.items.update', [$this->order, $foreign]), ['final_price' => '1000'])->assertNotFound();
});

it('laporan terlihat oleh client setelah dikirim', function () {
    afterPhoto($this->unitA);
    afterPhoto($this->unitB);
    $this->unitA->update(['final_price' => 85000, 'work_note' => 'Freon ditambah']);
    $this->unitB->update(['final_price' => 120000]);
    $this->post(route('teknisi.reports.submit', $this->order));

    $this->actingAs($this->order->client)->get(route('client.orders.show', $this->order))
        ->assertSee('Freon ditambah')
        ->assertSee('Rp217.000');
});
