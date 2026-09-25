<?php

namespace Database\Factories;

use App\Models\StaffLeave;
use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: cuti teknisi. Untuk helper: StaffLeave::factory()->for($helper, 'staff').
 *
 * @extends Factory<StaffLeave>
 */
class StaffLeaveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_type' => (new Technician)->getMorphClass(),
            'staff_id' => Technician::factory(),
            'leave_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'reason' => fake()->optional()->randomElement(['Sakit', 'Acara keluarga', 'Cuti tahunan']),
        ];
    }
}
