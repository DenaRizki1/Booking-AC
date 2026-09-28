<?php

namespace App\Jobs;

use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Services\DistanceService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hitung ulang customer_addresses.distance_km setelah titik kantor dipindah.
 * Order lama tidak disentuh (jarak & biaya transport sudah di-snapshot).
 */
class RecalculateAddressDistances implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(DistanceService $distances): void
    {
        $settings = CompanySettings::query()->firstOrFail();

        CustomerAddress::query()->chunkById(500, function ($addresses) use ($distances, $settings) {
            foreach ($addresses as $address) {
                $address->distance_km = $distances->fromOffice((float) $address->lat, (float) $address->lng, $settings);
                $address->saveQuietly();
            }
        });
    }
}
