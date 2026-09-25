<?php

namespace App\Support;

use App\Models\CompanySettings;
use App\Services\Estimates\ScheduleSlot;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;

/**
 * Pilihan tanggal (N hari ke depan) & jam mulai untuk client, dari ScheduleService.
 */
class BookingCalendar
{
    public const DAYS = 14;

    public function __construct(private readonly ScheduleService $schedule) {}

    /**
     * @return list<array{date: string, day: string, number: int, month: string, long: string, working: bool, slots: list<array{time: string, end: string, available: bool}>, full: bool}>
     */
    public function dates(int $minutes, bool $needsHelper, CompanySettings $settings): array
    {
        $dates = [];
        $today = CarbonImmutable::today();

        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = $today->addDays($offset);
            $working = $this->schedule->isWorkingDay($day, $settings);
            $available = $working
                ? array_map(fn (ScheduleSlot $slot) => $slot->label(), $this->schedule->availableSlots($day, $minutes, $needsHelper, $settings))
                : [];

            $dates[] = [
                'date' => $day->toDateString(),
                'day' => Format::day($day),
                'number' => $day->day,
                'month' => $day->translatedFormat('F Y'),
                'long' => Format::date($day),
                'working' => $working,
                'slots' => $working ? $this->candidateSlots($day, $minutes, $available, $settings) : [],
                'full' => $working && $available === [],
            ];
        }

        return $dates;
    }

    /**
     * Semua jam yang muat di jam operasional (belum lewat), ditandai tersedia atau tidak.
     *
     * @param  list<string>  $available
     * @return list<array{time: string, end: string, available: bool}>
     */
    private function candidateSlots(CarbonImmutable $day, int $minutes, array $available, CompanySettings $settings): array
    {
        $slots = [];
        $close = $day->setTimeFromTimeString($settings->close_time);

        for ($start = $day->setTimeFromTimeString($settings->open_time); $start->lessThan($close); $start = $start->addMinutes($settings->slot_interval_minutes)) {
            [$end, $blockedUntil] = $this->schedule->window($start, $minutes, $settings);

            if ($blockedUntil->greaterThan($close)) {
                break;
            }

            if ($start->lessThanOrEqualTo(now())) {
                continue;
            }

            $slots[] = ['time' => $start->format('H:i'), 'end' => $end->format('H:i'), 'available' => in_array($start->format('H:i'), $available, true)];
        }

        return $slots;
    }
}
