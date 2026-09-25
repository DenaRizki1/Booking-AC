<?php

namespace Database\Factories;

use App\Models\AcCapacity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcCapacity>
 */
class AcCapacityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pk = fake()->unique()->numberBetween(50, 9999) / 100;

        return [
            'label' => rtrim(rtrim(number_format($pk, 2, ',', ''), '0'), ',').' PK',
            'pk_value' => $pk,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
