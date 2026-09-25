<?php

namespace Database\Seeders;

use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

/**
 * 4 layanan + matriks harga PK × Tipe.
 * Cuci AC persis mockup Admin-Harga; layanan lain mengikuti "harga mulai" di mockup Client-Beranda.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $capacities = AcCapacity::query()->pluck('id', 'pk_value')->mapWithKeys(
            fn (int $id, string $pk) => [number_format((float) $pk, 2, '.', '') => $id]
        );
        $types = AcType::query()->pluck('id', 'name');

        foreach ($this->services() as $sortOrder => $definition) {
            $service = Service::firstOrCreate(['slug' => $definition['slug']], [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'requires_helper' => $definition['requires_helper'],
                'sort_order' => $sortOrder + 1,
            ]);

            $rows = [];
            foreach (AcMasterSeeder::CAPACITIES as $pk => $label) {
                foreach (AcMasterSeeder::TYPES as $type) {
                    [$min, $max, $minutes, $active] = $definition['price']($pk, $type);

                    $rows[] = [
                        'service_id' => $service->id,
                        'ac_capacity_id' => $capacities[$pk],
                        'ac_type_id' => $types[$type],
                        'price_min' => $min,
                        'price_max' => $max,
                        'est_minutes' => $minutes,
                        'is_active' => $active,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Tidak menimpa harga yang sudah diubah admin.
            ServicePrice::insertOrIgnore($rows);
        }
    }

    /**
     * @return list<array{name: string, slug: string, description: string, icon: string, requires_helper: bool, price: callable(string, string): array{int, int, int, bool}}>
     */
    private function services(): array
    {
        return [
            [
                'name' => 'Cuci AC',
                'slug' => 'cuci-ac',
                'description' => 'Cuci unit indoor & outdoor, bersihkan filter, evaporator, dan saluran pembuangan.',
                'icon' => 'droplets',
                'requires_helper' => false,
                'price' => fn (string $pk, string $type) => $this->cuciAc($pk, $type),
            ],
            [
                'name' => 'Perbaikan',
                'slug' => 'perbaikan',
                'description' => 'Pengecekan & perbaikan AC tidak dingin, bocor, berisik, atau mati. Sparepart dihitung terpisah.',
                'icon' => 'wrench',
                'requires_helper' => false,
                'price' => fn (string $pk, string $type) => $this->scaled($pk, $type, base: 100000, step: 25000, spread: 100000, inverter: 50000, minutes: 120),
            ],
            [
                'name' => 'Instalasi',
                'slug' => 'instalasi',
                'description' => 'Pasang unit baru termasuk bracket & pipa standar 3 meter. Wajib didampingi helper.',
                'icon' => 'drill',
                'requires_helper' => true,
                'price' => fn (string $pk, string $type) => $this->scaled($pk, $type, base: 350000, step: 50000, spread: 100000, inverter: 50000, minutes: (float) $pk >= 2 ? 150 : 120),
            ],
            [
                'name' => 'Isi Freon',
                'slug' => 'isi-freon',
                'description' => 'Tambah atau isi ulang freon (R32/R410A/R22) sesuai tekanan standar.',
                'icon' => 'thermometer-snowflake',
                'requires_helper' => false,
                'price' => fn (string $pk, string $type) => $this->scaled($pk, $type, base: 150000, step: 50000, spread: 100000, inverter: 50000, minutes: 60),
            ],
        ];
    }

    /**
     * Matriks Cuci AC sesuai mockup/Admin-Harga.html.
     *
     * @return array{int, int, int, bool}
     */
    private function cuciAc(string $pk, string $type): array
    {
        $inverter = $type === 'Inverter';

        return match ($pk) {
            '0.50', '0.75', '1.00' => $inverter ? [90000, 110000, 60, true] : [75000, 90000, 60, true],
            '1.50' => $inverter ? [110000, 130000, 90, true] : [90000, 110000, 60, true],
            '2.00' => $inverter ? [130000, 160000, 90, true] : [110000, 130000, 90, true],
            default => match ($type) {
                'Inverter' => [150000, 180000, 120, true],
                'Low Watt' => [130000, 160000, 90, false], // tidak dilayani
                default => [130000, 160000, 90, true],
            },
        };
    }

    /**
     * Harga naik per tingkat PK di atas 1 PK; tipe Inverter lebih mahal.
     *
     * @return array{int, int, int, bool}
     */
    private function scaled(string $pk, string $type, int $base, int $step, int $spread, int $inverter, int $minutes): array
    {
        $tier = match ($pk) {
            '1.50' => 1,
            '2.00' => 2,
            '2.50' => 3,
            default => 0,
        };

        $min = $base + $tier * $step + ($type === 'Inverter' ? $inverter : 0);

        return [$min, $min + $spread, $minutes, true];
    }
}
