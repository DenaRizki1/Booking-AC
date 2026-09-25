<?php

namespace App\Services;

use App\Exceptions\OutOfServiceAreaException;
use App\Models\CompanySettings;

/**
 * Biaya transport: gratis di dalam radius gratis; di luarnya
 * ceil(jarak − radius_gratis) × tarif_per_km; ditolak bila > radius maksimal.
 */
class TransportFeeCalculator
{
    public function isWithinRange(float $distanceKm, ?CompanySettings $settings = null): bool
    {
        $settings ??= CompanySettings::current();

        return $this->hundredths($distanceKm) <= $this->hundredths((float) $settings->max_radius_km);
    }

    /**
     * @throws OutOfServiceAreaException
     */
    public function feeFor(float $distanceKm, ?CompanySettings $settings = null): int
    {
        $settings ??= CompanySettings::current();

        if (! $this->isWithinRange($distanceKm, $settings)) {
            throw new OutOfServiceAreaException($distanceKm, (float) $settings->max_radius_km);
        }

        // Hitung dalam satuan 0,01 km agar pembulatan ke atas tidak terganggu galat float.
        $excess = $this->hundredths($distanceKm) - $this->hundredths((float) $settings->free_radius_km);

        if ($excess <= 0) {
            return 0;
        }

        return intdiv($excess + 99, 100) * $settings->fee_per_km;
    }

    private function hundredths(float $km): int
    {
        return (int) round($km * 100);
    }
}
