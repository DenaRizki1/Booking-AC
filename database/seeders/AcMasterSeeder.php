<?php

namespace Database\Seeders;

use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use Illuminate\Database\Seeder;

class AcMasterSeeder extends Seeder
{
    public const BRANDS = ['Daikin', 'Panasonic', 'LG', 'Sharp', 'Gree', 'Samsung', 'Mitsubishi', 'Polytron'];

    /** pk_value => label */
    public const CAPACITIES = [
        '0.50' => '½ PK',
        '0.75' => '¾ PK',
        '1.00' => '1 PK',
        '1.50' => '1½ PK',
        '2.00' => '2 PK',
        '2.50' => '2½ PK',
    ];

    public const TYPES = ['Standar', 'Low Watt', 'Inverter'];

    public function run(): void
    {
        foreach (self::BRANDS as $order => $name) {
            AcBrand::firstOrCreate(['name' => $name], ['sort_order' => $order + 1]);
        }

        $order = 0;
        foreach (self::CAPACITIES as $pk => $label) {
            AcCapacity::firstOrCreate(['pk_value' => $pk], ['label' => $label, 'sort_order' => ++$order]);
        }

        foreach (self::TYPES as $order => $name) {
            AcType::firstOrCreate(['name' => $name], ['sort_order' => $order + 1]);
        }
    }
}
