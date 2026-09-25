<?php

use App\Exceptions\OutOfServiceAreaException;
use App\Models\CompanySettings;
use App\Services\DistanceService;
use App\Services\TransportFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->settings = CompanySettings::factory()->make([
        'lat' => -6.2607, 'lng' => 106.8137,
        'free_radius_km' => 5, 'max_radius_km' => 25, 'fee_per_km' => 3000,
    ]);
    $this->calculator = new TransportFeeCalculator;
});

it('menghitung jarak Haversine dalam km dengan 2 desimal', function () {
    $distance = new DistanceService;

    expect($distance->between(0, 0, 1, 0))->toBe(111.19)
        ->and($distance->between(-6.2607, 106.8137, -6.2607, 106.8137))->toBe(0.0)
        ->and($distance->between(-6.1754, 106.8272, -6.9147, 107.6098))
        ->toBe($distance->between(-6.9147, 107.6098, -6.1754, 106.8272))
        ->toBeGreaterThan(115)->toBeLessThan(120);
});

it('menghitung jarak dari titik kantor', function () {
    $distance = (new DistanceService)->fromOffice(-6.2267, 106.8530, $this->settings);

    expect($distance)->toBe(5.76);
});

it('memakai pengaturan perusahaan tersimpan bila tidak diberikan', function () {
    CompanySettings::factory()->create(['lat' => 0, 'lng' => 0]);

    expect((new DistanceService)->fromOffice(1, 0))->toBe(111.19);
})->uses(RefreshDatabase::class);

it('menghitung biaya transport sesuai contoh di TASKS', function (float $km, int $fee) {
    expect($this->calculator->feeFor($km, $this->settings))->toBe($fee);
})->with([
    '8,4 km → Rp12.000' => [8.4, 12000],
    '4,9 km → gratis' => [4.9, 0],
    'tepat 5 km → gratis' => [5.0, 0],
    '5,01 km → 1 km dikenai' => [5.01, 3000],
    '12 km → Rp21.000 (contoh WORKFLOW)' => [12.0, 21000],
    'tepat 25 km → masih dilayani' => [25.0, 60000],
]);

it('tidak terganggu galat float saat selisih jarak bulat', function () {
    $settings = CompanySettings::factory()->make(['free_radius_km' => 3.3, 'max_radius_km' => 25, 'fee_per_km' => 3000]);

    // 6,3 − 3,3 di float = 3,0000000000000004 → harus tetap 3 km.
    expect($this->calculator->feeFor(6.3, $settings))->toBe(9000);
});

it('menolak alamat di luar radius maksimal', function () {
    expect($this->calculator->isWithinRange(26, $this->settings))->toBeFalse()
        ->and($this->calculator->isWithinRange(25, $this->settings))->toBeTrue();

    $this->calculator->feeFor(26, $this->settings);
})->throws(OutOfServiceAreaException::class, 'Alamat di luar jangkauan layanan (26,00 km, maksimal 25 km).');
