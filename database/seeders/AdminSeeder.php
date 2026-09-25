<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public const EMAIL = 'admin@booking-ac.test';

    public const PASSWORD = 'password';

    /**
     * Akun admin awal. Ganti password setelah login pertama di server produksi.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => self::EMAIL], [
            'name' => 'Administrator',
            'role' => UserRole::ADMIN,
            'password' => self::PASSWORD,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
