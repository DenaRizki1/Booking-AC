<?php

namespace Database\Factories;

use App\Models\AcType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcType>
 */
class AcTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Tipe '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
