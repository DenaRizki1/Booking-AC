<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Format tampilan: rupiah, durasi, tanggal (Bahasa Indonesia).
 */
final class Format
{
    /** Rp75.000 */
    public static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    /** Rp75.000 – Rp90.000 (atau satu nilai bila sama) */
    public static function rupiahRange(int $min, int $max): string
    {
        return $min === $max ? self::rupiah($min) : self::rupiah($min).' – '.self::rupiah($max);
    }

    /** Rp75rb · Rp1,2jt */
    public static function rupiahShort(int $amount): string
    {
        if ($amount >= 1_000_000) {
            return 'Rp'.self::decimal($amount / 1_000_000).'jt';
        }

        if ($amount >= 1_000) {
            return 'Rp'.self::decimal($amount / 1_000).'rb';
        }

        return self::rupiah($amount);
    }

    /** ±1 jam · ±2,5 jam · ±45 menit */
    public static function duration(int $minutes): string
    {
        return $minutes < 60 ? "±{$minutes} menit" : '±'.self::decimal($minutes / 60).' jam';
    }

    private const DAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    /** Sab (singkatan hari tanpa titik, seperti mockup) */
    public static function day(DateTimeInterface $date): string
    {
        return self::DAYS[Carbon::instance($date)->dayOfWeekIso];
    }

    /** Sab, 26 Sep */
    public static function shortDate(DateTimeInterface $date): string
    {
        return self::day($date).', '.Carbon::instance($date)->translatedFormat('j M');
    }

    /** Sab, 26 Sep 2026 */
    public static function date(DateTimeInterface $date): string
    {
        return self::day($date).', '.Carbon::instance($date)->translatedFormat('j M Y');
    }

    /** Sab, 26 Sep · 11:00–13:30 */
    public static function schedule(DateTimeInterface $start, DateTimeInterface $end, bool $withYear = false): string
    {
        $date = $withYear ? self::date($start) : self::shortDate($start);

        return $date.' · '.Carbon::instance($start)->format('H:i').'–'.Carbon::instance($end)->format('H:i');
    }

    /** 8,4 km */
    public static function km(float|string $distance): string
    {
        return number_format((float) $distance, 1, ',', '.').' km';
    }

    /** https://wa.me/62812… dari no. HP 08… */
    public static function whatsappUrl(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return 'https://wa.me/'.preg_replace('/^0/', '62', (string) preg_replace('/\D/', '', (string) $phone));
    }

    private static function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
