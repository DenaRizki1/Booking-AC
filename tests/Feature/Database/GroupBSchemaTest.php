<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('membuat tabel master AC sesuai ERD', function () {
    $common = [
        'id' => ['bigint unsigned', false],
        'is_active' => ['tinyint(1)', false],
        'sort_order' => ['smallint', false],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ];

    expectTableStructure('ac_brands', $common + ['name' => ['varchar(60)', false]]);
    expectTableStructure('ac_capacities', $common + [
        'label' => ['varchar(20)', false],
        'pk_value' => ['decimal(4,2)', false],
    ]);
    expectTableStructure('ac_types', $common + ['name' => ['varchar(40)', false]]);

    expect(tableIndexes('ac_brands'))->toHaveKey('ac_brands_name_unique')
        ->and(tableIndexes('ac_capacities'))->toHaveKey('ac_capacities_pk_value_unique')
        ->and(tableIndexes('ac_types'))->toHaveKey('ac_types_name_unique');
});

it('membuat tabel services sesuai ERD', function () {
    expectTableStructure('services', [
        'id' => ['bigint unsigned', false],
        'name' => ['varchar(80)', false],
        'slug' => ['varchar(100)', false],
        'description' => ['text', true],
        'icon' => ['varchar(50)', true],
        'requires_helper' => ['tinyint(1)', false],
        'warranty_days' => ['smallint unsigned', true],
        'is_active' => ['tinyint(1)', false],
        'sort_order' => ['smallint', false],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
        'deleted_at' => ['timestamp', true],
    ]);

    expect(tableIndexes('services'))->toHaveKey('services_slug_unique');
});

it('membuat tabel service_prices sesuai ERD', function () {
    expectTableStructure('service_prices', [
        'id' => ['bigint unsigned', false],
        'service_id' => ['bigint unsigned', false],
        'ac_capacity_id' => ['bigint unsigned', false],
        'ac_type_id' => ['bigint unsigned', false],
        'price_min' => ['int unsigned', false],
        'price_max' => ['int unsigned', false],
        'est_minutes' => ['smallint unsigned', false],
        'is_active' => ['tinyint(1)', false],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);

    expect(tableIndexes('service_prices')['service_prices_combination_unique'])
        ->toBe(['service_id', 'ac_capacity_id', 'ac_type_id']);
});

/**
 * @return array{service: int, capacity: int, type: int}
 */
function seedPriceMasters(): array
{
    return [
        'service' => DB::table('services')->insertGetId(['name' => 'Cuci AC', 'slug' => 'cuci-ac']),
        'capacity' => DB::table('ac_capacities')->insertGetId(['label' => '1 PK', 'pk_value' => 1]),
        'type' => DB::table('ac_types')->insertGetId(['name' => 'Standar']),
    ];
}

function priceRow(array $ids, int $min, int $max): array
{
    return [
        'service_id' => $ids['service'], 'ac_capacity_id' => $ids['capacity'], 'ac_type_id' => $ids['type'],
        'price_min' => $min, 'price_max' => $max, 'est_minutes' => 60,
    ];
}

it('menolak price_max lebih kecil dari price_min (CHECK)', function () {
    $ids = seedPriceMasters();

    DB::table('service_prices')->insert(priceRow($ids, 75000, 75000));
    DB::table('service_prices')->delete();

    DB::table('service_prices')->insert(priceRow($ids, 90000, 75000));
})->throws(QueryException::class);

it('hanya boleh satu harga per kombinasi layanan × PK × tipe', function () {
    $ids = seedPriceMasters();

    DB::table('service_prices')->insert(priceRow($ids, 75000, 90000));
    DB::table('service_prices')->insert(priceRow($ids, 80000, 95000));
})->throws(QueryException::class);

it('menghapus harga saat layanan dihapus, tapi menahan hapus PK yang dipakai', function () {
    $ids = seedPriceMasters();
    DB::table('service_prices')->insert(priceRow($ids, 75000, 90000));

    expect(fn () => DB::table('ac_capacities')->where('id', $ids['capacity'])->delete())
        ->toThrow(QueryException::class);

    DB::table('services')->where('id', $ids['service'])->delete();

    expect(DB::table('service_prices')->count())->toBe(0);
});
