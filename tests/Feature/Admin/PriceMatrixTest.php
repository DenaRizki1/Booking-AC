<?php

use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\PriceMatrixService;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->service = Service::factory()->create(['name' => 'Cuci AC']);
    $this->half = AcCapacity::factory()->create(['label' => '½ PK', 'pk_value' => 0.5, 'sort_order' => 1]);
    $this->one = AcCapacity::factory()->create(['label' => '1 PK', 'pk_value' => 1, 'sort_order' => 2]);
    $this->standard = AcType::factory()->create(['name' => 'Standar', 'sort_order' => 1]);
    $this->inverter = AcType::factory()->create(['name' => 'Inverter', 'sort_order' => 2]);
});

function priceCell(string $min, string $max, string $hours, bool $active = true): array
{
    return ['min' => $min, 'max' => $max, 'hours' => $hours, 'active' => $active ? '1' : '0'];
}

it('menampilkan grid PK × tipe termasuk sel kosong untuk PK/tipe baru', function () {
    ServicePrice::factory()->create([
        'service_id' => $this->service->id, 'ac_capacity_id' => $this->half->id, 'ac_type_id' => $this->standard->id,
        'price_min' => 75000, 'price_max' => 90000, 'est_minutes' => 90,
    ]);
    $new = AcCapacity::factory()->create(['label' => '3 PK', 'pk_value' => 3, 'sort_order' => 9]);

    $response = $this->get(route('admin.services.prices.edit', $this->service))->assertOk()
        ->assertSee('Isi cepat')
        ->assertSee('Harga per PK × Tipe')
        ->assertSeeInOrder(['½ PK', '1 PK', '3 PK']);

    $cells = $response->viewData('cells');
    expect($cells)->toHaveCount(6)
        ->and($cells["{$this->half->id}-{$this->standard->id}"])->toBe(['min' => '75.000', 'max' => '90.000', 'hours' => '1,5', 'active' => true])
        ->and($cells["{$new->id}-{$this->inverter->id}"])->toBe(['min' => '', 'max' => '', 'hours' => '', 'active' => true]);
});

it('menyimpan matriks dengan upsert: menambah, mengubah, dan mengosongkan sel', function () {
    $existing = ServicePrice::factory()->create([
        'service_id' => $this->service->id, 'ac_capacity_id' => $this->half->id, 'ac_type_id' => $this->standard->id,
        'price_min' => 50000, 'price_max' => 60000, 'est_minutes' => 60,
    ]);
    ServicePrice::factory()->create([
        'service_id' => $this->service->id, 'ac_capacity_id' => $this->one->id, 'ac_type_id' => $this->inverter->id,
    ]);

    $this->put(route('admin.services.prices.update', $this->service), ['cells' => [
        $this->half->id => [
            $this->standard->id => priceCell('75.000', 'Rp90.000', '1,5'),
            $this->inverter->id => priceCell('90000', '110000', '2', false),
        ],
        $this->one->id => [
            $this->standard->id => priceCell('', '', ''),
            $this->inverter->id => priceCell('', '', ''),
        ],
    ]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.services.prices.edit', $this->service))
        ->assertSessionHas('success', 'Matriks harga disimpan (2 kombinasi berharga, 1 dikosongkan).');

    $prices = $this->service->prices()->get()->keyBy(fn ($p) => $p->ac_capacity_id.'-'.$p->ac_type_id);
    expect($prices)->toHaveCount(2)
        ->and($prices["{$this->half->id}-{$this->standard->id}"]->id)->toBe($existing->id)
        ->and($prices["{$this->half->id}-{$this->standard->id}"]->only(['price_min', 'price_max', 'est_minutes', 'is_active']))
        ->toBe(['price_min' => 75000, 'price_max' => 90000, 'est_minutes' => 90, 'is_active' => true])
        ->and($prices["{$this->half->id}-{$this->inverter->id}"]->only(['price_min', 'price_max', 'est_minutes', 'is_active']))
        ->toBe(['price_min' => 90000, 'price_max' => 110000, 'est_minutes' => 120, 'is_active' => false]);
});

it('tidak menyentuh harga layanan lain', function () {
    $other = ServicePrice::factory()->create(['ac_capacity_id' => $this->half->id, 'ac_type_id' => $this->standard->id, 'price_min' => 1000, 'price_max' => 2000]);

    $this->put(route('admin.services.prices.update', $this->service), ['cells' => [
        $this->half->id => [$this->standard->id => priceCell('75000', '90000', '1')],
    ]])->assertSessionHasNoErrors();

    expect($other->fresh()->price_min)->toBe(1000);
});

it('memvalidasi tiap sel', function (array $cell, string $message) {
    $this->from(route('admin.services.prices.edit', $this->service))
        ->put(route('admin.services.prices.update', $this->service), ['cells' => [
            $this->half->id => [$this->inverter->id => $cell],
        ]])
        ->assertRedirect(route('admin.services.prices.edit', $this->service))
        ->assertSessionHasErrors(["cells.{$this->half->id}.{$this->inverter->id}" => "½ PK · Inverter: {$message}"]);

    expect(ServicePrice::query()->count())->toBe(0);
})->with([
    'min > max' => [priceCell('100.000', '90.000', '1'), 'harga mulai tidak boleh lebih besar dari harga sampai.'],
    'menit nol' => [priceCell('75.000', '90.000', '0'), 'estimasi durasi harus lebih dari 0 jam.'],
    'durasi bukan angka' => [priceCell('75.000', '90.000', 'satu'), 'estimasi durasi harus lebih dari 0 jam.'],
    'durasi kosong' => [priceCell('75.000', '90.000', ''), 'estimasi durasi harus lebih dari 0 jam.'],
    'durasi > 24 jam' => [priceCell('75.000', '90.000', '25'), 'estimasi durasi maksimal 24 jam.'],
    'harga mulai kosong' => [priceCell('', '90.000', '1'), 'harga mulai wajib diisi.'],
    'harga sampai kosong' => [priceCell('75.000', '', '1'), 'harga sampai wajib diisi.'],
]);

it('tidak menyimpan apa pun bila satu sel tidak valid', function () {
    $this->put(route('admin.services.prices.update', $this->service), ['cells' => [
        $this->half->id => [
            $this->standard->id => priceCell('75000', '90000', '1'),
            $this->inverter->id => priceCell('90000', '80000', '1'),
        ],
    ]])->assertSessionHasErrors();

    expect(ServicePrice::query()->count())->toBe(0);
});

it('mengembalikan input lama ke grid setelah validasi gagal', function () {
    $this->from(route('admin.services.prices.edit', $this->service))
        ->put(route('admin.services.prices.update', $this->service), ['cells' => [
            $this->half->id => [$this->inverter->id => priceCell('100.000', '90.000', '1')],
        ]]);

    $response = $this->get(route('admin.services.prices.edit', $this->service))->assertSee('Periksa sel berikut');
    expect($response->viewData('cells')["{$this->half->id}-{$this->inverter->id}"])
        ->toBe(['min' => '100.000', 'max' => '90.000', 'hours' => '1', 'active' => true]);
});

it('menolak id PK/tipe yang tidak dikenal', function () {
    $this->put(route('admin.services.prices.update', $this->service), ['cells' => [
        999999 => [$this->standard->id => priceCell('75000', '90000', '1')],
    ]])->assertSessionHasErrors(['cells' => 'Kapasitas atau tipe AC tidak dikenal. Muat ulang halaman.']);
});

it('PriceMatrixService memakai satu kunci unik per kombinasi', function () {
    $matrix = app(PriceMatrixService::class);
    $cell = ['price_min' => 1000, 'price_max' => 2000, 'est_minutes' => 30, 'is_active' => true];

    $matrix->save($this->service, [$this->one->id => [$this->standard->id => $cell]]);
    $matrix->save($this->service, [$this->one->id => [$this->standard->id => ['price_max' => 5000] + $cell]]);

    expect($this->service->prices()->count())->toBe(1)
        ->and($this->service->prices()->first()->price_max)->toBe(5000);
});
