<?php

use App\Support\Format;
use Carbon\CarbonImmutable;

it('memformat rupiah', function () {
    expect(Format::rupiah(185000))->toBe('Rp185.000')
        ->and(Format::rupiahRange(185000, 220000))->toBe('Rp185.000 – Rp220.000')
        ->and(Format::rupiahRange(0, 0))->toBe('Rp0')
        ->and(Format::rupiahShort(75000))->toBe('Rp75rb')
        ->and(Format::rupiahShort(1250000))->toBe('Rp1,3jt')
        ->and(Format::rupiahShort(500))->toBe('Rp500');
});

it('memformat durasi dalam jam', function () {
    expect(Format::duration(60))->toBe('±1 jam')
        ->and(Format::duration(150))->toBe('±2,5 jam')
        ->and(Format::duration(90))->toBe('±1,5 jam')
        ->and(Format::duration(45))->toBe('±45 menit');
});

it('memformat tanggal & jadwal Bahasa Indonesia', function () {
    $start = CarbonImmutable::parse('2026-09-26 11:00');

    expect(Format::shortDate($start))->toBe('Sab, 26 Sep')
        ->and(Format::date(CarbonImmutable::parse('2026-09-25')))->toBe('Jum, 25 Sep 2026')
        ->and(Format::schedule($start, $start->addMinutes(150)))->toBe('Sab, 26 Sep · 11:00–13:30')
        ->and(Format::km(8.4))->toBe('8,4 km');
});

it('membuat tautan WhatsApp dari no. HP', function () {
    expect(Format::whatsappUrl('081234567890'))->toBe('https://wa.me/6281234567890')
        ->and(Format::whatsappUrl(null))->toBeNull();
});
