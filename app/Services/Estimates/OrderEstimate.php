<?php

namespace App\Services\Estimates;

/**
 * Total estimasi order (belum termasuk biaya transport).
 */
final readonly class OrderEstimate
{
    public int $priceMin;

    public int $priceMax;

    public int $totalMinutes;

    public bool $requiresHelper;

    /**
     * @param  list<EstimateLine>  $lines  urut sesuai input unit
     */
    public function __construct(public array $lines)
    {
        $this->priceMin = array_sum(array_map(fn (EstimateLine $line) => $line->priceMin, $lines));
        $this->priceMax = array_sum(array_map(fn (EstimateLine $line) => $line->priceMax, $lines));
        $this->totalMinutes = array_sum(array_map(fn (EstimateLine $line) => $line->minutes, $lines));
        $this->requiresHelper = in_array(true, array_map(fn (EstimateLine $line) => $line->requiresHelper, $lines), true);
    }
}
