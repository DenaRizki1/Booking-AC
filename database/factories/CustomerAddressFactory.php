<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Rumah', 'Kantor', 'Kos', 'Rumah Orang Tua']),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'landmark' => fake()->optional()->randomElement(['Depan masjid', 'Samping minimarket', 'Pagar hitam', 'Dekat pos satpam']),
            'lat' => fake()->latitude(-6.33, -6.18),
            'lng' => fake()->longitude(106.73, 106.90),
            'distance_km' => fake()->randomFloat(2, 0.5, 20),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
