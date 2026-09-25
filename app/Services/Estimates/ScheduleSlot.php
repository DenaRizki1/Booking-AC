<?php

namespace App\Services\Estimates;

use Carbon\CarbonImmutable;

/**
 * Satu pilihan jam untuk client: mulai, selesai kerja, dan akhir rentang terkunci (+ buffer).
 */
final readonly class ScheduleSlot
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $blockedUntil,
    ) {}

    public function label(): string
    {
        return $this->start->format('H:i');
    }
}
