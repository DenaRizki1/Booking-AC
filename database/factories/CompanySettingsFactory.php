<?php

namespace Database\Factories;

use App\Models\CompanySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySettings>
 */
class CompanySettingsFactory extends Factory
{
    protected $model = CompanySettings::class;

    /**
     * Nilai default sesuai ERD; titik kantor di Jakarta Selatan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => 'SejukAC',
            'phone' => '081234567890',
            'email' => 'halo@sejukac.test',
            'address' => 'Jl. Kemang Raya No. 10, Mampang Prapatan, Jakarta Selatan',
            'lat' => -6.2607000,
            'lng' => 106.8137000,
            'open_time' => '08:00:00',
            'close_time' => '18:00:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'slot_interval_minutes' => 60,
            'travel_buffer_minutes' => 30,
            'free_radius_km' => 5,
            'max_radius_km' => 25,
            'fee_per_km' => 3000,
            'cancel_limit_hours' => 3,
            'warranty_days' => 7,
            'bank_name' => 'BCA',
            'bank_account_no' => '1234567890',
            'bank_account_name' => 'PT Sejuk AC Indonesia',
        ];
    }
}
