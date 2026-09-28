<?php

use App\Jobs\RecalculateAddressDistances;
use Illuminate\Support\Facades\Artisan;

Artisan::command('addresses:recalculate-distances', function () {
    RecalculateAddressDistances::dispatchSync();
    $this->info('Jarak semua alamat client sudah dihitung ulang dari titik kantor.');
})->purpose('Hitung ulang customer_addresses.distance_km dari titik kantor saat ini');
