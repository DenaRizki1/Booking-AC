<?php

use App\Jobs\RecalculateAddressDistances;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('addresses:recalculate-distances', function () {
    RecalculateAddressDistances::dispatchSync();
    $this->info('Jarak semua alamat client sudah dihitung ulang dari titik kantor.');
})->purpose('Hitung ulang customer_addresses.distance_km dari titik kantor saat ini');

// Jadwal (jalankan `php artisan schedule:work` atau cron/launchd `schedule:run` tiap menit).
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
