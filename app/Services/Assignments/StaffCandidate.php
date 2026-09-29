<?php

namespace App\Services\Assignments;

use App\Models\Helper;
use App\Models\Order;
use App\Models\Technician;
use Illuminate\Support\Collection;

/**
 * Satu teknisi/helper di panel "Tugaskan tim": status Free / Bentrok / Cuti + order hari itu (mini timeline).
 */
final class StaffCandidate
{
    public const FREE = 'free';

    public const CONFLICT = 'conflict';

    public const LEAVE = 'leave';

    /**
     * @param  Collection<int, Order>  $dayOrders  order lain milik staf ini pada hari yang sama
     * @param  Collection<int, Order>  $conflicts  order yang beririsan dengan order ini
     */
    public function __construct(
        public readonly Technician|Helper $staff,
        public readonly string $status,
        public readonly Collection $dayOrders,
        public readonly Collection $conflicts,
        public readonly bool $suggested = false,
        public readonly bool $current = false,
    ) {}

    public function isFree(): bool
    {
        return $this->status === self::FREE;
    }

    public function name(): string
    {
        return $this->staff instanceof Technician ? $this->staff->user->name : $this->staff->name;
    }

    public function label(): string
    {
        return match ($this->status) {
            self::FREE => 'Free',
            self::CONFLICT => 'Bentrok',
            default => 'Cuti',
        };
    }
}
