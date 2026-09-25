<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature test memakai TestCase Laravel + RefreshDatabase (database
| MySQL `booking_ac_test`, lihat phpunit.xml).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/**
 * Pastikan struktur tabel sesuai kamus data ERD.
 *
 * @param  array<string, array{0: string, 1: bool}>  $expected  kolom => [tipe MySQL, nullable]
 */
function expectTableStructure(string $table, array $expected): void
{
    $columns = collect(Schema::getColumns($table))->keyBy('name');

    expect($columns->keys()->sort()->values()->all())
        ->toBe(collect(array_keys($expected))->sort()->values()->all(), "Kolom tabel {$table}");

    foreach ($expected as $name => [$type, $nullable]) {
        expect($columns[$name]['type'])->toBe($type, "Tipe {$table}.{$name}")
            ->and($columns[$name]['nullable'])->toBe($nullable, "Nullable {$table}.{$name}");
    }
}

/**
 * Nama index (unik/biasa) → daftar kolomnya.
 *
 * @return array<string, list<string>>
 */
function tableIndexes(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->mapWithKeys(fn (array $index) => [$index['name'] => $index['columns']])
        ->all();
}
