<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Services\Estimates\ScheduleSlot;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;

// Senin, 5 Oktober 2026 (hari kerja). Jam operasional 08:00–18:00, slot 60 menit, buffer 30 menit.
const SCHEDULE_TEST_DAY = '2026-10-05';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(SCHEDULE_TEST_DAY.' 07:00'));
    CompanySettings::factory()->create();
    $this->schedule = new ScheduleService;
});

function mondayAt(string $time, string $date = SCHEDULE_TEST_DAY): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}");
}

/**
 * @param  list<ScheduleSlot>  $slots
 * @return list<string>
 */
function slotLabels(array $slots): array
{
    return array_map(fn (ScheduleSlot $slot) => $slot->label(), $slots);
}

describe('isFree', function () {
    it('mengikuti contoh WORKFLOW bagian 9', function () {
        $tech = Technician::factory()->create();

        // #1: 2 unit cuci (2 × 1 jam) jam 09:00 → terkunci 09:00–11:30.
        Order::factory()->at(mondayAt('09:00'), 120)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $tech->id]);

        // #2: perbaikan 2 jam jam 11:30 → 11:30–14:00, tidak bentrok.
        expect($this->schedule->isFree($tech, mondayAt('11:30'), mondayAt('14:00')))->toBeTrue();
        Order::factory()->at(mondayAt('11:30'), 120)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $tech->id]);

        // #3: cuci 1 jam jam 13:00 → bentrok dengan #2.
        expect($this->schedule->isFree($tech, mondayAt('13:00'), mondayAt('14:30')))->toBeFalse();
    });

    it('tidak bentrok bila mulai tepat saat rentang lama berakhir', function () {
        $tech = Technician::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 60)->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $tech->id]); // 09:00–10:30

        expect($this->schedule->isFree($tech, mondayAt('10:30'), mondayAt('12:00')))->toBeTrue()
            ->and($this->schedule->isFree($tech, mondayAt('10:00'), mondayAt('11:30')))->toBeFalse()
            ->and($this->schedule->isFree($tech, mondayAt('07:30'), mondayAt('09:00')))->toBeTrue()
            ->and($this->schedule->isFree($tech, mondayAt('08:00'), mondayAt('09:01')))->toBeFalse();
    });

    it('mengabaikan order yang sedang dicek (ganti teknisi / assign ulang)', function () {
        $tech = Technician::factory()->create();
        $order = Order::factory()->at(mondayAt('09:00'), 60)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $tech->id]);

        expect($this->schedule->isFree($tech, mondayAt('09:00'), mondayAt('10:30')))->toBeFalse()
            ->and($this->schedule->isFree($tech, mondayAt('09:00'), mondayAt('10:30'), $order->id))->toBeTrue();
    });

    it('tidak menghitung order yang ditolak atau dibatalkan', function (OrderStatus $status) {
        $tech = Technician::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 60)->withStatus($status)->create(['technician_id' => $tech->id]);

        expect($this->schedule->isFree($tech, mondayAt('09:00'), mondayAt('10:30')))->toBeTrue();
    })->with([OrderStatus::DITOLAK, OrderStatus::DIBATALKAN]);

    it('menghitung order yang sudah selesai atau lunas sebagai terkunci', function (OrderStatus $status) {
        $tech = Technician::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 60)->withStatus($status)->create(['technician_id' => $tech->id]);

        expect($this->schedule->isFree($tech, mondayAt('09:00'), mondayAt('10:30')))->toBeFalse();
    })->with([OrderStatus::SELESAI, OrderStatus::LUNAS]);

    it('menganggap staf cuti tidak free di tanggal cutinya saja', function () {
        $tech = Technician::factory()->create();
        StaffLeave::factory()->for($tech, 'staff')->create(['leave_date' => SCHEDULE_TEST_DAY]);

        expect($this->schedule->isFree($tech, mondayAt('09:00'), mondayAt('10:30')))->toBeFalse()
            ->and($this->schedule->isFree($tech, mondayAt('09:00', '2026-10-06'), mondayAt('10:30', '2026-10-06')))->toBeTrue();
    });

    it('menganggap teknisi nonaktif, akun nonaktif, atau terhapus tidak free', function () {
        $inactive = Technician::factory()->inactive()->create();
        $accountOff = Technician::factory()->create();
        $accountOff->user->update(['is_active' => false]);
        $deleted = Technician::factory()->create();
        $deleted->delete();

        foreach ([$inactive, $accountOff, $deleted] as $tech) {
            expect($this->schedule->isFree($tech, mondayAt('09:00'), mondayAt('10:30')))->toBeFalse();
        }
    });

    it('mengecek helper dengan aturan yang sama', function () {
        $helper = Helper::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 60)->withStatus(OrderStatus::DITUGASKAN)->create(['helper_id' => $helper->id]);
        $onLeave = Helper::factory()->create();
        StaffLeave::factory()->for($onLeave, 'staff')->create(['leave_date' => SCHEDULE_TEST_DAY]);

        expect($this->schedule->isFree($helper, mondayAt('10:00'), mondayAt('11:30')))->toBeFalse()
            ->and($this->schedule->isFree($helper, mondayAt('10:30'), mondayAt('12:00')))->toBeTrue()
            ->and($this->schedule->isFree($onLeave, mondayAt('13:00'), mondayAt('14:30')))->toBeFalse();
    });
});

describe('freeTechnicians & freeHelpers', function () {
    it('hanya mengembalikan staf yang free', function () {
        [$busy, $free, $onLeave] = Technician::factory()->count(3)->create();
        Technician::factory()->inactive()->create();
        Order::factory()->at(mondayAt('09:00'), 60)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $busy->id]);
        StaffLeave::factory()->for($onLeave, 'staff')->create(['leave_date' => SCHEDULE_TEST_DAY]);

        [$busyHelper, $freeHelper] = Helper::factory()->count(2)->create();
        Order::factory()->at(mondayAt('09:00'), 60)->withStatus(OrderStatus::DITUGASKAN)->create(['helper_id' => $busyHelper->id]);

        expect($this->schedule->freeTechnicians(mondayAt('09:30'), mondayAt('11:00'))->modelKeys())->toBe([$free->id])
            ->and($this->schedule->freeHelpers(mondayAt('09:30'), mondayAt('11:00'))->modelKeys())->toBe([$freeHelper->id]);
    });
});

describe('availableSlots', function () {
    it('menampilkan slot tiap jam dari jam buka sampai selesai + buffer ≤ jam tutup', function () {
        Technician::factory()->create();

        // 60 menit + buffer 30 → mulai paling lambat 16:30, slot per jam → terakhir 16:00.
        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))
            ->toBe(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00']);

        // 8 jam kerja: 08:00 → 16:30, 09:00 → 17:30, 10:00 → 18:30 (lewat jam tutup).
        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 480, false)))->toBe(['08:00', '09:00']);
    });

    it('mengisi selesai kerja & akhir rentang terkunci pada slot', function () {
        Technician::factory()->create();

        $slot = $this->schedule->availableSlots(mondayAt('00:00'), 150, false)[0];

        expect($slot->start->format('H:i'))->toBe('08:00')
            ->and($slot->end->format('H:i'))->toBe('10:30')
            ->and($slot->blockedUntil->format('H:i'))->toBe('11:00');
    });

    it('tetap tersedia selama minimal 1 teknisi free', function () {
        [$a, $b] = Technician::factory()->count(2)->create();
        Order::factory()->at(mondayAt('09:00'), 120)->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $a->id]);

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))->toContain('09:00', '10:00');

        // Teknisi kedua juga sibuk 09:00–11:30 → slot yang beririsan hilang.
        Order::factory()->at(mondayAt('09:00'), 120)->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $b->id]);

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))
            ->toBe(['12:00', '13:00', '14:00', '15:00', '16:00']);
    });

    it('menghitung order MENUNGGU yang belum di-assign sebagai pemakai kapasitas', function () {
        Technician::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 120)->create(); // MENUNGGU, 09:00–11:30, belum ada teknisi

        // 1 teknisi, 1 order menunggu → jam yang beririsan penuh.
        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))
            ->toBe(['12:00', '13:00', '14:00', '15:00', '16:00']);

        // Teknisi kedua → kapasitas cukup lagi.
        Technician::factory()->create();

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))->toContain('09:00', '10:00', '11:00');
    });

    it('tidak menghitung order menunggu yang sudah ditolak atau dibatalkan', function () {
        Technician::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 120)->withStatus(OrderStatus::DIBATALKAN)->create(['technician_id' => null]);

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))->toContain('09:00');
    });

    it('menghitung order wajib helper yang belum dapat helper sebagai pemakai kapasitas helper', function () {
        Technician::factory()->count(2)->create();
        Helper::factory()->create();
        Order::factory()->at(mondayAt('09:00'), 60)->create(['requires_helper' => true]); // 09:00–10:30

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, true)))->not->toContain('09:00')
            ->and(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))->toContain('09:00');
    });

    it('mewajibkan helper free bila order butuh helper', function () {
        Technician::factory()->create();

        expect($this->schedule->availableSlots(mondayAt('00:00'), 60, true))->toBe([]);

        $helper = Helper::factory()->create();
        Order::factory()->at(mondayAt('08:00'), 180)->withStatus(OrderStatus::DITUGASKAN)->create(['helper_id' => $helper->id]); // 08:00–11:30

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, true)))
            ->toBe(['12:00', '13:00', '14:00', '15:00', '16:00']);
    });

    it('kosong bila semua teknisi cuti', function () {
        $tech = Technician::factory()->create();
        StaffLeave::factory()->for($tech, 'staff')->create(['leave_date' => SCHEDULE_TEST_DAY]);

        expect($this->schedule->availableSlots(mondayAt('00:00'), 60, false))->toBe([]);
    });

    it('kosong di hari libur (bukan hari kerja)', function () {
        Technician::factory()->create();

        expect($this->schedule->availableSlots(mondayAt('00:00', '2026-10-11'), 60, false))->toBe([]) // Minggu
            ->and($this->schedule->isWorkingDay(mondayAt('00:00', '2026-10-10')))->toBeTrue(); // Sabtu
    });

    it('tidak menampilkan jam yang sudah lewat', function () {
        Technician::factory()->create();
        $this->travelTo(mondayAt('10:15'));

        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false))[0])->toBe('11:00')
            ->and($this->schedule->availableSlots(mondayAt('00:00', '2026-10-03'), 60, false))->toBe([]);
    });

    it('mengikuti pengaturan jam operasional, interval slot, dan buffer', function () {
        Technician::factory()->create();
        CompanySettings::current()->update([
            'open_time' => '09:00:00', 'close_time' => '12:00:00',
            'slot_interval_minutes' => 30, 'travel_buffer_minutes' => 15,
        ]);

        // 60 + 15 menit harus selesai ≤ 12:00 → mulai paling lambat 10:45 → slot 30 menit terakhir 10:30.
        expect(slotLabels($this->schedule->availableSlots(mondayAt('00:00'), 60, false)))
            ->toBe(['09:00', '09:30', '10:00', '10:30']);
    });
});

describe('jam operasional', function () {
    it('memvalidasi hari kerja, jam buka, dan jam tutup', function () {
        expect($this->schedule->withinOperatingHours(mondayAt('08:00'), mondayAt('18:00')))->toBeTrue()
            ->and($this->schedule->withinOperatingHours(mondayAt('07:30'), mondayAt('09:00')))->toBeFalse()
            ->and($this->schedule->withinOperatingHours(mondayAt('16:30'), mondayAt('18:30')))->toBeFalse()
            ->and($this->schedule->withinOperatingHours(mondayAt('09:00', '2026-10-11'), mondayAt('10:30', '2026-10-11')))->toBeFalse();
    });

    it('menghitung selesai kerja & blocked_until dari durasi + buffer', function () {
        [$end, $blockedUntil] = $this->schedule->window(mondayAt('09:00'), 150);

        expect($end->format('H:i'))->toBe('11:30')
            ->and($blockedUntil->format('H:i'))->toBe('12:00');
    });
});
