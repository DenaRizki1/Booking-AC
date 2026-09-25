<?php

namespace App\Services;

use App\Models\CompanySettings;

/**
 * Jarak garis lurus (Haversine) dalam km, dibulatkan 2 desimal.
 */
class DistanceService
{
    public const EARTH_RADIUS_KM = 6371.0;

    public function between(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $fromLatRad = deg2rad($fromLat);
        $toLatRad = deg2rad($toLat);
        $deltaLat = $toLatRad - $fromLatRad;
        $deltaLng = deg2rad($toLng - $fromLng);

        $a = sin($deltaLat / 2) ** 2 + cos($fromLatRad) * cos($toLatRad) * sin($deltaLng / 2) ** 2;

        return round(2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a))), 2);
    }

    /**
     * Jarak dari titik kantor (company_settings) ke koordinat tujuan.
     */
    public function fromOffice(float $lat, float $lng, ?CompanySettings $settings = null): float
    {
        $settings ??= CompanySettings::current();

        return $this->between((float) $settings->lat, (float) $settings->lng, $lat, $lng);
    }
}
