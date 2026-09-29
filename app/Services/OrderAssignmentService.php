<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\OrderAssignmentException;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use App\Services\Assignments\StaffCandidate;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin menugaskan teknisi (+ helper) ke order.
 * Aturan bentrok sama dengan ScheduleService: start_baru < blocked_until_lama DAN blocked_until_baru > start_lama.
 */
class OrderAssignmentService
{
    /** Status yang masih bisa di-assign / diganti timnya. */
    public const ASSIGNABLE = [OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI];

    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly OrderStatusService $statuses,
    ) {}

    public function canAssign(Order $order): bool
    {
        return in_array($order->status, self::ASSIGNABLE, true);
    }

    /**
     * Kandidat teknisi & helper aktif untuk order ini, diurutkan: disarankan, free, bentrok, cuti.
     *
     * @return array{technicians: Collection<int, StaffCandidate>, helpers: Collection<int, StaffCandidate>}
     */
    public function candidates(Order $order): array
    {
        $suggestedTechnicianId = $order->order_type === OrderType::GARANSI ? $order->parentOrder?->technician_id : null;

        $technicians = Technician::query()
            ->where('is_active', true)
            ->whereHas('user', fn (Builder $user) => $user->where('is_active', true))
            ->with('user:id,name')
            ->get();

        $helpers = Helper::query()->where('is_active', true)->orderBy('name')->get();

        return [
            'technicians' => $this->rank($this->evaluate($order, $technicians, 'technician_id', $suggestedTechnicianId)),
            'helpers' => $this->rank($this->evaluate($order, $helpers, 'helper_id', null)),
        ];
    }

    /**
     * Tugaskan / ganti tim. Mengunci baris order & staf lalu cek ulang bentrok sebelum menyimpan.
     *
     * @return string pesan hasil untuk admin
     *
     * @throws OrderAssignmentException
     */
    public function assign(Order $order, Technician $technician, ?Helper $helper, User $admin): string
    {
        return DB::transaction(function () use ($order, $technician, $helper, $admin) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $this->canAssign($locked)) {
                throw new OrderAssignmentException("Order berstatus {$locked->status->label()} tidak bisa di-assign lagi.");
            }
            if ($locked->requires_helper && $helper === null) {
                throw new OrderAssignmentException('Order ini wajib didampingi helper. Pilih helper.', 'helper_id');
            }

            // Kunci staf agar dua admin tidak menugaskan staf yang sama ke jam yang sama.
            Technician::query()->whereKey($technician->getKey())->lockForUpdate()->first();
            if ($helper) {
                Helper::query()->whereKey($helper->getKey())->lockForUpdate()->first();
            }

            $start = CarbonImmutable::instance($locked->scheduled_start);
            $until = CarbonImmutable::instance($locked->blocked_until);

            if (! $this->schedule->isFree($technician, $start, $until, $locked->id)) {
                throw new OrderAssignmentException("{$technician->user->name} tidak tersedia di jadwal ini (bentrok, cuti, atau nonaktif). Pilih teknisi lain.");
            }
            if ($helper && ! $this->schedule->isFree($helper, $start, $until, $locked->id)) {
                throw new OrderAssignmentException("Helper {$helper->name} tidak tersedia di jadwal ini. Pilih helper lain.", 'helper_id');
            }

            $sameTechnician = $locked->technician_id === $technician->id;
            $sameHelper = $locked->helper_id === $helper?->id;

            if ($locked->status !== OrderStatus::MENUNGGU && $sameTechnician) {
                if ($sameHelper) {
                    return 'Tidak ada perubahan tim.';
                }

                // Hanya helper yang berganti: teknisi tidak perlu konfirmasi ulang.
                $locked->update(['helper_id' => $helper?->id]);

                return $helper ? "Helper diganti menjadi {$helper->name}." : 'Helper dilepas dari order.';
            }

            $team = $technician->user->name.($helper ? " + {$helper->name}" : '');
            $note = $locked->technician_id === null
                ? "Ditugaskan ke {$team}"
                : 'Tim diganti: '.($locked->technician?->user->name ?? '-')." → {$team}";

            $this->statuses->transition($locked, OrderStatus::DITUGASKAN, $admin, $note, [
                'technician_id' => $technician->id,
                'helper_id' => $helper?->id,
            ]);

            return "Order ditugaskan ke {$team}. Menunggu konfirmasi teknisi.";
        });
    }

    /**
     * @template TStaff of Technician|Helper
     *
     * @param  Collection<int, TStaff>  $staff
     * @return Collection<int, StaffCandidate>
     */
    private function evaluate(Order $order, Collection $staff, string $column, ?int $suggestedId): Collection
    {
        $start = CarbonImmutable::instance($order->scheduled_start);
        $until = CarbonImmutable::instance($order->blocked_until);
        $day = $start->startOfDay();
        $ids = $staff->map(fn (Technician|Helper $member) => $member->getKey())->all();

        $dayOrders = Order::query()
            ->locksSchedule()
            ->whereIn($column, $ids)
            ->whereKeyNot($order->id)
            ->where('scheduled_start', '<', $day->addDay())
            ->where('blocked_until', '>', $day)
            ->orderBy('scheduled_start')
            ->get(['id', 'code', $column, 'scheduled_start', 'scheduled_end', 'blocked_until', 'status'])
            ->groupBy($column);

        $morph = $staff->first()?->getMorphClass();
        $onLeave = $morph === null ? collect() : DB::table('staff_leaves')
            ->where('staff_type', $morph)
            ->whereIn('staff_id', $ids)
            ->whereDate('leave_date', $day->toDateString())
            ->pluck('staff_id')
            ->flip();

        return $staff->map(function (Technician|Helper $member) use ($order, $column, $start, $until, $dayOrders, $onLeave, $suggestedId) {
            /** @var Collection<int, Order> $orders */
            $orders = $dayOrders->get($member->id, collect());
            $conflicts = $orders->filter(fn (Order $other) => $start->lt($other->blocked_until) && $until->gt($other->scheduled_start))->values();

            $status = match (true) {
                $onLeave->has($member->id) => StaffCandidate::LEAVE,
                $conflicts->isNotEmpty() => StaffCandidate::CONFLICT,
                default => StaffCandidate::FREE,
            };

            return new StaffCandidate($member, $status, $orders, $conflicts, $member->id === $suggestedId, $order->getAttribute($column) === $member->id);
        });
    }

    /**
     * @param  Collection<int, StaffCandidate>  $candidates
     * @return Collection<int, StaffCandidate>
     */
    private function rank(Collection $candidates): Collection
    {
        $order = [StaffCandidate::FREE => 0, StaffCandidate::CONFLICT => 1, StaffCandidate::LEAVE => 2];

        return $candidates
            ->sortBy([
                fn (StaffCandidate $a, StaffCandidate $b) => [$b->current, $b->suggested] <=> [$a->current, $a->suggested],
                fn (StaffCandidate $a, StaffCandidate $b) => $order[$a->status] <=> $order[$b->status],
                fn (StaffCandidate $a, StaffCandidate $b) => $a->name() <=> $b->name(),
            ])
            ->values();
    }

    /** "Bentrok ORD-2609-0014 14:30" untuk keterangan kandidat. */
    public static function conflictNote(StaffCandidate $candidate): string
    {
        $first = $candidate->conflicts->first();

        return $first ? "Bentrok {$first->code} ".Format::schedule($first->scheduled_start, $first->scheduled_end) : '';
    }
}
