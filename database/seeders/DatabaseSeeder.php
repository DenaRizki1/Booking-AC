<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal wajib (semua environment) + data demo (hanya lokal).
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CompanySettingsSeeder::class,
            AcMasterSeeder::class,
            ServiceSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
