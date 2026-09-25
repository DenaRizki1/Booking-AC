<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Layanan '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'wrench',
            'requires_helper' => false,
            'warranty_days' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function requiresHelper(): static
    {
        return $this->state(fn () => ['requires_helper' => true]);
    }
}
