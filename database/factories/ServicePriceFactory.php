<?php

namespace Database\Factories;

use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePrice>
 */
class ServicePriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->numberBetween(15, 40) * 5000;

        return [
            'service_id' => Service::factory(),
            'ac_capacity_id' => AcCapacity::factory(),
            'ac_type_id' => AcType::factory(),
            'price_min' => $min,
            'price_max' => $min + fake()->numberBetween(1, 6) * 5000,
            'est_minutes' => fake()->randomElement([60, 90, 120]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
