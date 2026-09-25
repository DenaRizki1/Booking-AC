<?php

use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\AcMasterSeeder;
use Database\Seeders\ServiceSeeder;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->seed([AcMasterSeeder::class, ServiceSeeder::class]);
    $this->client = User::factory()->create();
    $this->address = CustomerAddress::factory()->for($this->client)->default()->create(['distance_km' => 8.4]);
});

function wizardUnit(string $service = 'cuci-ac', string $pk = '1.00', string $type = 'Standar', string $brand = 'Daikin'): array
{
    return [
        'ac_brand_id' => AcBrand::where('name', $brand)->value('id'),
        'ac_capacity_id' => AcCapacity::where('pk_value', $pk)->value('id'),
        'ac_type_id' => AcType::where('name', $type)->value('id'),
        'service_id' => Service::where('slug', $service)->value('id'),
    ];
}

it('mengarahkan client tanpa alamat ke tambah alamat lalu kembali ke order', function () {
    $this->address->delete();

    $this->actingAs($this->client)->get('/order/baru')
        ->assertRedirect('/alamat/tambah?redirect=%2Forder%2Fbaru')
        ->assertSessionHas('info');
});

it('menampilkan langkah unit dengan katalog & layanan terpilih dari beranda', function () {
    $response = $this->actingAs($this->client)->get('/order/baru?service=instalasi')
        ->assertOk()
        ->assertSee('Buat Order')
        ->assertSee('Lanjut pilih jadwal')
        ->assertSee('Terapkan ke semua unit');

    $units = $response->viewData('units');
    expect($units)->toHaveCount(1)
        ->and($units[0]['service_id'])->toBe(Service::where('slug', 'instalasi')->value('id'))
        ->and($units[0]['ac_capacity_id'])->toBe(AcCapacity::where('pk_value', '1.00')->value('id'))
        ->and($response->viewData('addressId'))->toBe($this->address->id);

    // 2½ PK Low Watt tidak dilayani untuk Cuci AC.
    $availability = $response->viewData('catalog')['availability'];
    $key = AcCapacity::where('pk_value', '2.50')->value('id').'-'.AcType::where('name', 'Low Watt')->value('id');
    expect($availability[$key])->not->toContain(Service::where('slug', 'cuci-ac')->value('id'));
});

it('menghitung estimasi per unit & total lewat endpoint JSON', function () {
    $query = http_build_query([
        'customer_address_id' => $this->address->id,
        'units' => [wizardUnit('cuci-ac', '1.00', 'Standar'), wizardUnit('cuci-ac', '1.50', 'Inverter', 'LG')],
    ]);

    $this->actingAs($this->client)->getJson("/order/estimate?{$query}")
        ->assertOk()
        ->assertJson([
            'complete' => true,
            'units' => [
                ['index' => 0, 'available' => true, 'price_label' => 'Rp75.000 – Rp90.000', 'duration_label' => '±1 jam'],
                ['index' => 1, 'available' => true, 'price_label' => 'Rp110.000 – Rp130.000', 'duration_label' => '±1,5 jam'],
            ],
            'total' => ['price_min' => 185000, 'price_max' => 220000, 'minutes' => 150, 'price_label' => 'Rp185.000 – Rp220.000', 'duration_label' => '±2,5 jam'],
            'transport_fee' => 12000,
            'transport_label' => 'Rp12.000',
        ]);
});

it('menandai unit tanpa harga & unit belum lengkap di estimasi', function () {
    $query = http_build_query(['units' => [
        wizardUnit('cuci-ac', '2.50', 'Low Watt'),
        ['ac_capacity_id' => 1, 'ac_type_id' => 1, 'service_id' => ''],
    ]]);

    $this->actingAs($this->client)->getJson("/order/estimate?{$query}")
        ->assertOk()
        ->assertJson([
            'complete' => false,
            'units' => [
                ['index' => 0, 'available' => false, 'error' => 'Layanan ini belum tersedia untuk PK & tipe tersebut.'],
                ['index' => 1, 'available' => false, 'error' => null],
            ],
            'transport_fee' => null,
        ]);
});

it('tidak memakai alamat client lain untuk biaya transport', function () {
    $foreign = CustomerAddress::factory()->create(['distance_km' => 20]);
    $query = http_build_query(['customer_address_id' => $foreign->id, 'units' => [wizardUnit()]]);

    $this->actingAs($this->client)->getJson("/order/estimate?{$query}")->assertJson(['transport_fee' => null]);
});

it('menyimpan draft otomatis sehingga aman saat refresh', function () {
    $this->actingAs($this->client)
        ->putJson('/order/baru/draft', ['customer_address_id' => $this->address->id, 'units' => [wizardUnit('perbaikan'), wizardUnit('isi-freon')]])
        ->assertOk();

    $units = $this->actingAs($this->client)->get('/order/baru')->viewData('units');

    expect($units)->toHaveCount(2)
        ->and($units[1]['service_id'])->toBe(Service::where('slug', 'isi-freon')->value('id'));
});

it('lanjut ke jadwal bila semua unit valid', function () {
    $this->actingAs($this->client)
        ->post('/order/baru', ['customer_address_id' => $this->address->id, 'units' => [wizardUnit(), wizardUnit('instalasi')]])
        ->assertRedirect('/order/baru/jadwal')
        ->assertSessionHasNoErrors();

    expect(session('order_draft'))
        ->toMatchArray(['address_id' => $this->address->id, 'ready' => true])
        ->and(session('order_draft.units'))->toHaveCount(2);
});

it('menolak unit tanpa harga dengan pesan per unit', function () {
    $this->actingAs($this->client)
        ->post('/order/baru', ['customer_address_id' => $this->address->id, 'units' => [wizardUnit(), wizardUnit('cuci-ac', '2.50', 'Low Watt')]])
        ->assertSessionHasErrors(['units.1.service_id' => 'Unit 2: layanan Cuci AC belum tersedia untuk 2½ PK · Low Watt.']);
});

it('memvalidasi isian unit & alamat', function () {
    $this->actingAs($this->client)
        ->post('/order/baru', ['customer_address_id' => CustomerAddress::factory()->create()->id, 'units' => [['ac_capacity_id' => 1]]])
        ->assertSessionHasErrors([
            'customer_address_id' => 'Alamat tidak ditemukan.',
            'units.0.ac_brand_id' => 'Pilih merk AC.',
            'units.0.service_id' => 'Pilih layanan.',
        ]);

    $this->actingAs($this->client)->post('/order/baru', ['customer_address_id' => $this->address->id, 'units' => []])
        ->assertSessionHasErrors(['units' => 'Tambahkan minimal 1 unit AC.']);
});

it('menolak alamat yang kini di luar jangkauan', function () {
    $this->address->update(['distance_km' => 30]);

    $this->actingAs($this->client)
        ->post('/order/baru', ['customer_address_id' => $this->address->id, 'units' => [wizardUnit()]])
        ->assertSessionHasErrors(['customer_address_id' => 'Alamat ini di luar jangkauan layanan. Pilih alamat lain.']);
});

it('menolak merk atau layanan yang nonaktif', function () {
    AcBrand::where('name', 'Daikin')->update(['is_active' => false]);

    $this->actingAs($this->client)
        ->post('/order/baru', ['customer_address_id' => $this->address->id, 'units' => [wizardUnit()]])
        ->assertSessionHasErrors(['units.0.ac_brand_id']);
});
