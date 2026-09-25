<?php

namespace App\Services\Estimates;

use App\Models\Service;
use App\Models\ServicePrice;

/**
 * Estimasi satu unit AC (dari satu sel matriks harga).
 */
final readonly class EstimateLine
{
    public function __construct(
        public ServicePrice $price,
        public Service $service,
        public int $priceMin,
        public int $priceMax,
        public int $minutes,
        public bool $requiresHelper,
    ) {}

    public static function fromPrice(ServicePrice $price): self
    {
        return new self(
            price: $price,
            service: $price->service,
            priceMin: $price->price_min,
            priceMax: $price->price_max,
            minutes: $price->est_minutes,
            requiresHelper: $price->service->requires_helper,
        );
    }
}
