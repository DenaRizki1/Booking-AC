<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Default: client yang login lewat Google (tanpa password).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'google_id' => (string) fake()->unique()->numerify('1##################'),
            'avatar' => null,
            'phone' => fake()->numerify('08##########'),
            'role' => UserRole::CLIENT,
            'password' => null,
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->staff(UserRole::ADMIN);
    }

    public function teknisi(): static
    {
        return $this->staff(UserRole::TEKNISI);
    }

    /**
     * Client yang belum melengkapi no. HP (login Google pertama).
     */
    public function withoutPhone(): static
    {
        return $this->state(fn () => ['phone' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * Admin & teknisi login dengan email + password (default: "password").
     */
    private function staff(UserRole $role): static
    {
        return $this->state(fn () => [
            'role' => $role,
            'google_id' => null,
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }
}
