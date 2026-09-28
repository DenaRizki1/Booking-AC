<?php

use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\OrderItem;
use App\Models\ServicePrice;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('menampilkan tab merk, PK, dan tipe dengan urutan sort_order', function () {
    AcBrand::factory()->create(['name' => 'Panasonic', 'sort_order' => 2]);
    AcBrand::factory()->create(['name' => 'Daikin', 'sort_order' => 1]);
    AcBrand::factory()->create(['name' => 'Gree', 'sort_order' => 3, 'is_active' => false]);

    $this->get(route('admin.ac.index'))
        ->assertOk()
        ->assertSee(['Merk', 'Kapasitas (PK)', 'Tipe AC'])
        ->assertSeeInOrder(['Daikin', 'Panasonic', 'Gree'])
        ->assertSee('Nonaktif');

    AcCapacity::factory()->create(['label' => '1½ PK', 'pk_value' => 1.5]);
    $this->get(route('admin.ac.index', 'pk'))->assertOk()->assertSee('1½ PK')->assertSee('Nilai PK');

    AcType::factory()->create(['name' => 'Inverter']);
    $this->get(route('admin.ac.index', 'tipe'))->assertOk()->assertSee('Inverter');
});

it('menolak jenis data yang tidak dikenal', function () {
    $this->get('/admin/data-ac/warna')->assertNotFound();
});

it('hanya bisa diakses admin', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.ac.index'))->assertForbidden();
});

it('menambah merk di urutan terakhir', function () {
    AcBrand::factory()->create(['sort_order' => 4]);

    $this->post(route('admin.ac.store', 'merk'), ['name' => 'Sharp', 'is_active' => '1'])
        ->assertRedirect(route('admin.ac.index', 'merk'))
        ->assertSessionHas('success', 'Merk ditambahkan.');

    $brand = AcBrand::query()->where('name', 'Sharp')->sole();
    expect($brand->is_active)->toBeTrue()->and($brand->sort_order)->toBe(5);
});

it('menambah kapasitas dan tipe', function () {
    $this->post(route('admin.ac.store', 'pk'), ['label' => '2 PK', 'pk_value' => '2', 'is_active' => '0'])->assertSessionHasNoErrors();
    $this->post(route('admin.ac.store', 'tipe'), ['name' => 'Low Watt', 'is_active' => '1'])->assertSessionHasNoErrors();

    expect(AcCapacity::query()->where('pk_value', 2)->sole()->is_active)->toBeFalse()
        ->and(AcType::query()->where('name', 'Low Watt')->exists())->toBeTrue();
});

it('memvalidasi input dan keunikan', function (string $kind, array $payload, string $field, string $message) {
    AcBrand::factory()->create(['name' => 'Daikin']);
    AcCapacity::factory()->create(['label' => '1 PK', 'pk_value' => 1]);
    AcType::factory()->create(['name' => 'Inverter']);

    $this->from(route('admin.ac.index', $kind))
        ->post(route('admin.ac.store', $kind), $payload + ['kind' => $kind])
        ->assertRedirect(route('admin.ac.index', $kind))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'merk kosong' => ['merk', ['name' => ''], 'name', 'Nama merk wajib diisi.'],
    'merk ganda' => ['merk', ['name' => 'Daikin'], 'name', 'Nama merk ini sudah ada.'],
    'merk terlalu panjang' => ['merk', ['name' => str_repeat('a', 61)], 'name', 'Nama merk maksimal 60 karakter.'],
    'PK ganda' => ['pk', ['label' => 'Satu PK', 'pk_value' => '1.00'], 'pk_value', 'Kapasitas dengan nilai PK ini sudah ada.'],
    'label PK ganda' => ['pk', ['label' => '1 PK', 'pk_value' => '1.1'], 'label', 'Label ini sudah ada.'],
    'PK bukan angka' => ['pk', ['label' => 'X', 'pk_value' => 'satu'], 'pk_value', 'Nilai PK harus berupa angka.'],
    'tipe ganda' => ['tipe', ['name' => 'Inverter'], 'name', 'Nama tipe ini sudah ada.'],
]);

it('membuka kembali modal dengan input lama saat validasi gagal', function () {
    $this->from(route('admin.ac.index', 'merk'))
        ->post(route('admin.ac.store', 'merk'), ['name' => '', 'kind' => 'merk', 'record_id' => '']);

    $this->get(route('admin.ac.index', 'merk'))
        ->assertSee('x-data="{ show: true }"', false);
});

it('mengubah data tanpa bentrok dengan dirinya sendiri', function () {
    $brand = AcBrand::factory()->create(['name' => 'Daikin', 'is_active' => true]);

    $this->put(route('admin.ac.update', ['merk', $brand->id]), ['name' => 'Daikin', 'is_active' => '0'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Merk disimpan.');

    expect($brand->fresh()->is_active)->toBeFalse();
});

it('mengaktifkan dan menonaktifkan data', function () {
    $type = AcType::factory()->create(['name' => 'Standar', 'is_active' => true]);

    $this->patch(route('admin.ac.toggle', ['tipe', $type->id]))
        ->assertSessionHas('success', 'Standar dinonaktifkan dan tidak bisa dipilih client.');
    expect($type->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.ac.toggle', ['tipe', $type->id]))->assertSessionHas('success', 'Standar diaktifkan.');
    expect($type->fresh()->is_active)->toBeTrue();
});

it('memindahkan urutan naik dan turun', function () {
    $a = AcBrand::factory()->create(['name' => 'A', 'sort_order' => 1]);
    $b = AcBrand::factory()->create(['name' => 'B', 'sort_order' => 2]);
    $c = AcBrand::factory()->create(['name' => 'C', 'sort_order' => 3]);

    $this->patch(route('admin.ac.move', ['merk', $c->id]), ['direction' => 'up'])->assertRedirect(route('admin.ac.index', 'merk'));
    expect(AcBrand::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['A', 'C', 'B']);

    $this->patch(route('admin.ac.move', ['merk', $a->id]), ['direction' => 'down']);
    expect(AcBrand::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['C', 'A', 'B']);

    // Yang paling atas tidak bisa naik lagi.
    $this->patch(route('admin.ac.move', ['merk', $c->id]), ['direction' => 'up']);
    expect(AcBrand::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['C', 'A', 'B']);
});

it('menghapus data yang belum dipakai order beserta harganya', function () {
    $capacity = AcCapacity::factory()->create(['label' => '5 PK']);
    ServicePrice::factory()->count(2)->create(['ac_capacity_id' => $capacity->id]);

    $this->delete(route('admin.ac.destroy', ['pk', $capacity->id]))
        ->assertSessionHas('success', '5 PK dihapus.');

    expect(AcCapacity::query()->find($capacity->id))->toBeNull()
        ->and(ServicePrice::query()->where('ac_capacity_id', $capacity->id)->count())->toBe(0);
});

it('tidak menghapus data yang sudah dipakai order', function (string $kind, string $column) {
    $item = OrderItem::factory()->create();
    $id = $item->getAttribute($column);

    $this->delete(route('admin.ac.destroy', [$kind, $id]))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Nonaktifkan saja'));

    expect($item->fresh()->getAttribute($column))->toBe($id);
    $this->get(route('admin.ac.index', $kind))->assertSee('tidak bisa dihapus karena sudah dipakai di order');
})->with([
    ['merk', 'ac_brand_id'],
    ['pk', 'ac_capacity_id'],
    ['tipe', 'ac_type_id'],
]);

it('data nonaktif tidak muncul di form order client', function () {
    AcBrand::factory()->create(['name' => 'MerkAktif']);
    AcBrand::factory()->create(['name' => 'MerkLama', 'is_active' => false]);

    $client = User::factory()->create(['phone' => '081234567890']);
    CustomerAddress::factory()->for($client)->default()->create();

    $this->actingAs($client)->get(route('client.orders.create'))
        ->assertOk()
        ->assertSee('MerkAktif')
        ->assertDontSee('MerkLama');
});
