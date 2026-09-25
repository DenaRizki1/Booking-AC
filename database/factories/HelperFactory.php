<?php

namespace Database\Factories;

use App\Models\Helper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Helper>
 */
class HelperFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name('male'),
            'phone' => fake()->numerify('08##########'),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
