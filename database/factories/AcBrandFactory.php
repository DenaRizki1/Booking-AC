<?php

namespace Database\Factories;

use App\Models\AcBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcBrand>
 */
class AcBrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_substr(fake()->unique()->company(), 0, 60),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
