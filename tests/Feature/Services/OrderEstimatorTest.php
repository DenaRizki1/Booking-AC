<?php

use App\Exceptions\UnavailableUnitsException;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Services\OrderEstimator;
use Database\Seeders\AcMasterSeeder;
use Database\Seeders\ServiceSeeder;

beforeEach(function () {
    $this->seed([AcMasterSeeder::class, ServiceSeeder::class]);
    $this->estimator = new OrderEstimator;
});

/**
 * @return array{service_id: int, ac_capacity_id: int, ac_type_id: int}
 */
function unit(string $service, string $pk, string $type): array
{
    return [
        'service_id' => Service::where('slug', $service)->value('id'),
        'ac_capacity_id' => AcCapacity::where('pk_value', $pk)->value('id'),
        'ac_type_id' => AcType::where('name', $type)->value('id'),
    ];
}

it('menghitung contoh 2 unit di mockup: Rp185.000–Rp220.000, 150 menit', function () {
    $estimate = $this->estimator->estimate([
        unit('cuci-ac', '1.00', 'Standar'),   // Daikin 1 PK Standar
        unit('cuci-ac', '1.50', 'Inverter'),  // LG 1½ PK Inverter
    ]);

    expect($estimate->priceMin)->toBe(185000)
        ->and($estimate->priceMax)->toBe(220000)
        ->and($estimate->totalMinutes)->toBe(150)
        ->and($estimate->requiresHelper)->toBeFalse()
        ->and($estimate->lines)->toHaveCount(2)
        ->and($estimate->lines[0]->priceMin)->toBe(75000)
        ->and($estimate->lines[1]->minutes)->toBe(90)
        ->and($estimate->lines[1]->service->name)->toBe('Cuci AC');
});

it('menghitung unit yang sama lebih dari sekali (salin unit)', function () {
    $estimate = $this->estimator->estimate(array_fill(0, 3, unit('cuci-ac', '1.00', 'Standar')));

    expect([$estimate->priceMin, $estimate->priceMax, $estimate->totalMinutes])->toBe([225000, 270000, 180]);
});

it('menandai order butuh helper bila ada layanan wajib helper', function () {
    $estimate = $this->estimator->estimate([
        unit('cuci-ac', '1.00', 'Standar'),
        unit('instalasi', '1.00', 'Standar'),
    ]);

    expect($estimate->requiresHelper)->toBeTrue()
        ->and($estimate->lines[1]->requiresHelper)->toBeTrue();
});

it('memberi error per unit untuk kombinasi yang tidak dilayani', function () {
    try {
        $this->estimator->estimate([
            unit('cuci-ac', '1.00', 'Standar'),
            unit('cuci-ac', '2.50', 'Low Watt'), // nonaktif di matriks
        ]);
        $this->fail('Seharusnya melempar UnavailableUnitsException.');
    } catch (UnavailableUnitsException $e) {
        expect($e->unitErrors)->toBe([
            1 => 'Unit 2: layanan Cuci AC belum tersedia untuk 2½ PK · Low Watt.',
        ]);
    }
});

it('mengabaikan harga bila layanan, PK, atau tipe dinonaktifkan', function (string $model) {
    $unit = unit('isi-freon', '1.00', 'Standar');

    match ($model) {
        'service' => Service::whereKey($unit['service_id'])->update(['is_active' => false]),
        'capacity' => AcCapacity::whereKey($unit['ac_capacity_id'])->update(['is_active' => false]),
        'type' => AcType::whereKey($unit['ac_type_id'])->update(['is_active' => false]),
    };

    $this->estimator->estimate([$unit]);
})->with(['service', 'capacity', 'type'])->throws(UnavailableUnitsException::class);

it('mengabaikan layanan yang sudah dihapus', function () {
    $unit = unit('perbaikan', '1.00', 'Standar');
    Service::find($unit['service_id'])->delete();

    $this->estimator->estimate([$unit]);
})->throws(UnavailableUnitsException::class, 'Unit 1: data layanan, PK, atau tipe AC tidak valid.');

it('memakai harga terbaru dari matriks', function () {
    $unit = unit('cuci-ac', '1.00', 'Standar');
    ServicePrice::where($unit)->update(['price_min' => 80000, 'price_max' => 95000, 'est_minutes' => 75]);

    $estimate = $this->estimator->estimate([$unit]);

    expect([$estimate->priceMin, $estimate->priceMax, $estimate->totalMinutes])->toBe([80000, 95000, 75]);
});

it('menolak daftar unit kosong', function () {
    $this->estimator->estimate([]);
})->throws(InvalidArgumentException::class, 'Order minimal berisi 1 unit AC.');
