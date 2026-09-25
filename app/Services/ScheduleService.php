<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Technician;
use App\Services\Estimates\ScheduleSlot;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cek bentrok jadwal teknisi/helper & slot jam yang tersedia (WORKFLOW bagian 9, ERD bagian 8).
 *
 * Rentang terkunci order = [scheduled_start, blocked_until), blocked_until = selesai kerja + buffer perjalanan.
 * Dua rentang bentrok bila start_baru < blocked_until_lama DAN blocked_until_baru > start_lama.
 */
class ScheduleService
{
    /**
     * Staf aktif, tidak cuti di tanggal tersebut, dan tidak punya order lain yang beririsan.
     */
    public function isFree(
        Technician|Helper $staff,
        DateTimeInterface $start,
        DateTimeInterface $blockedUntil,
        ?int $ignoreOrderId = null,
    ): bool {
        $query = $staff instanceof Technician
            ? $this->withoutOverlappingOrders($this->activeTechnicians($start), $start, $blockedUntil, $ignoreOrderId)
            : $this->withoutOverlappingOrders($this->activeHelpers($start), $start, $blockedUntil, $ignoreOrderId);

        return $query->whereKey($staff->getKey())->exists();
    }

    /**
     * @return Collection<int, Technician>
     */
    public function freeTechnicians(DateTimeInterface $start, DateTimeInterface $blockedUntil, ?int $ignoreOrderId = null): Collection
    {
        return $this->withoutOverlappingOrders($this->activeTechnicians($start), $start, $blockedUntil, $ignoreOrderId)->get();
    }

    /**
     * @return Collection<int, Helper>
     */
    public function freeHelpers(DateTimeInterface $start, DateTimeInterface $blockedUntil, ?int $ignoreOrderId = null): Collection
    {
        return $this->withoutOverlappingOrders($this->activeHelpers($start), $start, $blockedUntil, $ignoreOrderId)->get();
    }

    /**
     * Selesai kerja & akhir rentang terkunci untuk order yang mulai di $start.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [scheduled_end, blocked_until]
     */
    public function window(DateTimeInterface $start, int $totalMinutes, ?CompanySettings $settings = null): array
    {
        $settings ??= CompanySettings::current();
        $end = CarbonImmutable::instance($start)->addMinutes($totalMinutes);

        return [$end, $end->addMinutes($settings->travel_buffer_minutes)];
    }

    /**
     * Hari kerja, mulai ≥ jam buka, dan blocked_until ≤ jam tutup.
     */
    public function withinOperatingHours(DateTimeInterface $start, DateTimeInterface $blockedUntil, ?CompanySettings $settings = null): bool
    {
        $settings ??= CompanySettings::current();
        $start = CarbonImmutable::instance($start);
        $blockedUntil = CarbonImmutable::instance($blockedUntil);

        return $this->isWorkingDay($start, $settings)
            && $start->greaterThanOrEqualTo($start->setTimeFromTimeString($settings->open_time))
            && $blockedUntil->lessThanOrEqualTo($start->setTimeFromTimeString($settings->close_time));
    }

    public function isWorkingDay(DateTimeInterface $date, ?CompanySettings $settings = null): bool
    {
        $settings ??= CompanySettings::current();

        return in_array(CarbonImmutable::instance($date)->dayOfWeekIso, array_map('intval', $settings->working_days), true);
    }

    /**
     * Slot jam yang bisa dipilih client pada tanggal tertentu: tiap slot_interval_minutes dari jam buka,
     * hanya hari kerja, selesai (+ buffer) sebelum jam tutup, belum lewat, dan ada minimal 1 teknisi
     * (+ 1 helper bila perlu) yang free.
     *
     * Order yang belum di-assign (MENUNGGU) ikut memakai kapasitas: slot tersedia hanya bila jumlah
     * teknisi free > jumlah order tanpa teknisi yang beririsan (sama untuk helper pada order wajib helper).
     *
     * @return list<ScheduleSlot>
     */
    public function availableSlots(DateTimeInterface $date, int $totalMinutes, bool $needsHelper, ?CompanySettings $settings = null): array
    {
        $settings ??= CompanySettings::current();
        $day = CarbonImmutable::instance($date)->startOfDay();

        if ($totalMinutes <= 0 || ! $this->isWorkingDay($day, $settings)) {
            return [];
        }

        $open = $day->setTimeFromTimeString($settings->open_time);
        $close = $day->setTimeFromTimeString($settings->close_time);
        $now = CarbonImmutable::now();

        // Muat jadwal semua staf yang tersedia hari itu sekali saja, lalu cek per slot di memori.
        $technicians = $this->activeTechnicians($open)->with(['orders' => $this->ordersBetween($open, $close)])->get();
        $helpers = $needsHelper ? $this->activeHelpers($open)->with(['orders' => $this->ordersBetween($open, $close)])->get() : null;
        $unassigned = Order::query()
            ->locksSchedule()
            ->overlapping($open, $close)
            ->where(fn (Builder $query) => $query->whereNull('technician_id')
                ->orWhere(fn (Builder $query) => $query->where('requires_helper', true)->whereNull('helper_id')))
            ->get(['id', 'technician_id', 'helper_id', 'requires_helper', 'scheduled_start', 'blocked_until']);

        $slots = [];

        for ($start = $open; $start->lessThan($close); $start = $start->addMinutes($settings->slot_interval_minutes)) {
            [$end, $blockedUntil] = $this->window($start, $totalMinutes, $settings);

            if ($blockedUntil->greaterThan($close)) {
                break;
            }

            if ($start->lessThanOrEqualTo($now)) {
                continue;
            }

            $pending = $unassigned->filter(fn (Order $order) => $this->overlaps($order, $start, $blockedUntil));

            if ($this->freeCount($technicians, $start, $blockedUntil) <= $pending->whereNull('technician_id')->count()) {
                continue;
            }

            if ($helpers !== null && $this->freeCount($helpers, $start, $blockedUntil) <= $pending->where('requires_helper', true)->whereNull('helper_id')->count()) {
                continue;
            }

            $slots[] = new ScheduleSlot($start, $end, $blockedUntil);
        }

        return $slots;
    }

    /**
     * Teknisi aktif (akun login juga aktif) yang tidak cuti pada tanggal $day.
     *
     * @return Builder<Technician>
     */
    private function activeTechnicians(DateTimeInterface $day): Builder
    {
        return Technician::query()
            ->where('is_active', true)
            ->whereHas('user', fn (Builder $user) => $user->where('is_active', true))
            ->whereDoesntHave('leaves', $this->leaveOn($day));
    }

    /**
     * Helper aktif yang tidak cuti pada tanggal $day.
     *
     * @return Builder<Helper>
     */
    private function activeHelpers(DateTimeInterface $day): Builder
    {
        return Helper::query()
            ->where('is_active', true)
            ->whereDoesntHave('leaves', $this->leaveOn($day));
    }

    /**
     * Saring staf yang tidak punya order pengunci jadwal beririsan dengan rentang baru.
     *
     * @template TStaff of Technician|Helper
     *
     * @param  Builder<TStaff>  $query
     * @return Builder<TStaff>
     */
    private function withoutOverlappingOrders(Builder $query, DateTimeInterface $start, DateTimeInterface $blockedUntil, ?int $ignoreOrderId): Builder
    {
        return $query->whereDoesntHave('orders', fn ($orders) => $orders
            ->locksSchedule()
            ->overlapping($start, $blockedUntil)
            ->when($ignoreOrderId, fn (Builder $query) => $query->whereKeyNot($ignoreOrderId)));
    }

    private function leaveOn(DateTimeInterface $day): Closure
    {
        return fn (Builder $leaves) => $leaves->whereDate('leave_date', CarbonImmutable::instance($day)->toDateString());
    }

    /**
     * Eager load order pengunci jadwal yang beririsan dengan jam operasional hari itu.
     */
    private function ordersBetween(CarbonImmutable $open, CarbonImmutable $close): Closure
    {
        return fn ($orders) => $orders
            ->locksSchedule()
            ->overlapping($open, $close)
            ->select(['id', 'technician_id', 'helper_id', 'scheduled_start', 'blocked_until']);
    }

    /**
     * Jumlah staf yang tidak punya order beririsan dengan rentang tersebut.
     *
     * @param  Collection<int, Technician>|Collection<int, Helper>  $staff
     */
    private function freeCount(Collection $staff, CarbonImmutable $start, CarbonImmutable $blockedUntil): int
    {
        return $staff->reject(fn (Technician|Helper $person) => $person->orders->contains(
            fn (Order $order) => $this->overlaps($order, $start, $blockedUntil)
        ))->count();
    }

    private function overlaps(Order $order, CarbonImmutable $start, CarbonImmutable $blockedUntil): bool
    {
        return $order->scheduled_start->lessThan($blockedUntil) && $order->blocked_until->greaterThan($start);
    }
}
