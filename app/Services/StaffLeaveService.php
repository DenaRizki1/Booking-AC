<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Helper;
use App\Models\Order;
use App\Models\StaffLeave;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cuti teknisi/helper: rentang tanggal disimpan satu baris per hari (staff_leaves).
 * Cuti tidak membatalkan order; admin diberi daftar order yang bentrok untuk di-assign ulang.
 */
class StaffLeaveService
{
    public const MAX_DAYS = 62;

    /**
     * @return array{added: int, conflicts: Collection<int, Order>}
     */
    public function add(Technician|Helper $staff, CarbonImmutable $from, CarbonImmutable $to, ?string $reason = null): array
    {
        $now = now();
        $rows = [];
        foreach (CarbonPeriod::create($from->startOfDay(), $to->startOfDay()) as $day) {
            $rows[] = [
                'staff_type' => $staff->getMorphClass(),
                'staff_id' => $staff->getKey(),
                'leave_date' => $day->toDateString(),
                'reason' => $reason,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Tanggal yang sudah tercatat cuti dilewati (unique staff_type, staff_id, leave_date).
        $added = StaffLeave::query()->insertOrIgnore($rows);

        return ['added' => $added, 'conflicts' => $this->conflicts($staff, $from, $to)];
    }

    /**
     * Order yang sudah ditugaskan ke staf ini dan dijadwalkan pada rentang tanggal tersebut.
     *
     * @return Collection<int, Order>
     */
    public function conflicts(Technician|Helper $staff, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->where($staff instanceof Technician ? 'technician_id' : 'helper_id', $staff->getKey())
            ->whereIn('status', [OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI, OrderStatus::DALAM_PERJALANAN, OrderStatus::DIKERJAKAN])
            ->where('scheduled_start', '>=', $from->startOfDay())
            ->where('scheduled_start', '<', $to->addDay()->startOfDay())
            ->orderBy('scheduled_start')
            ->get(['id', 'code', 'scheduled_start', 'status']);
    }
}
