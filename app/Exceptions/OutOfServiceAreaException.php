<?php

namespace App\Exceptions;

use DomainException;

/**
 * Alamat lebih jauh dari radius maksimal layanan — order tidak bisa dibuat.
 */
class OutOfServiceAreaException extends DomainException
{
    public function __construct(
        public readonly float $distanceKm,
        public readonly float $maxRadiusKm,
    ) {
        parent::__construct(sprintf(
            'Alamat di luar jangkauan layanan (%s km, maksimal %s km).',
            number_format($distanceKm, 2, ',', '.'),
            number_format($maxRadiusKm, 0, ',', '.'),
        ));
    }
}
