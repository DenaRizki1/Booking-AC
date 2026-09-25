<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MENUNGGU = 'MENUNGGU';
    case DITUGASKAN = 'DITUGASKAN';
    case DIKONFIRMASI = 'DIKONFIRMASI';
    case DALAM_PERJALANAN = 'DALAM_PERJALANAN';
    case DIKERJAKAN = 'DIKERJAKAN';
    case SELESAI = 'SELESAI';
    case LUNAS = 'LUNAS';
    case DITOLAK = 'DITOLAK';
    case DIBATALKAN = 'DIBATALKAN';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU => 'Menunggu',
            self::DITUGASKAN => 'Ditugaskan',
            self::DIKONFIRMASI => 'Dikonfirmasi',
            self::DALAM_PERJALANAN => 'Dalam Perjalanan',
            self::DIKERJAKAN => 'Dikerjakan',
            self::SELESAI => 'Selesai',
            self::LUNAS => 'Lunas',
            self::DITOLAK => 'Ditolak',
            self::DIBATALKAN => 'Dibatalkan',
        };
    }

    /**
     * Class Tailwind untuk badge status (warna sesuai mockup/StyleGuide.html).
     */
    public function color(): string
    {
        return match ($this) {
            self::MENUNGGU => 'bg-warning-50 text-warning-900',
            self::DITUGASKAN => 'bg-white text-blue-800 border-[1.5px] border-dashed border-primary',
            self::DIKONFIRMASI => 'bg-blue-100 text-blue-800',
            self::DALAM_PERJALANAN => 'bg-primary text-white',
            self::DIKERJAKAN => 'bg-navy text-white',
            self::SELESAI => 'bg-success-50 text-success-700',
            self::LUNAS => 'bg-success text-white',
            self::DITOLAK => 'bg-danger-50 text-danger-700',
            self::DIBATALKAN => 'bg-surface-bg text-muted',
        };
    }

    /**
     * Status yang mengunci jadwal teknisi/helper (dipakai cek bentrok).
     */
    public function locksSchedule(): bool
    {
        return ! in_array($this, [self::DITOLAK, self::DIBATALKAN], true);
    }

    /**
     * Nilai status yang mengunci jadwal, untuk query `whereIn`.
     *
     * @return list<string>
     */
    public static function scheduleLockingValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->locksSchedule()),
        ));
    }
}
