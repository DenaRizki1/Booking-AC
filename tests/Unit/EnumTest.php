<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PhotoKind;
use App\Enums\UserRole;

it('memakai nilai enum persis seperti ERD', function () {
    expect(array_column(UserRole::cases(), 'value'))->toBe(['client', 'admin', 'teknisi'])
        ->and(array_column(OrderType::cases(), 'value'))->toBe(['REGULER', 'GARANSI'])
        ->and(array_column(PaymentMethod::cases(), 'value'))->toBe(['TUNAI', 'TRANSFER'])
        ->and(array_column(PhotoKind::cases(), 'value'))->toBe(['KELUHAN', 'SEBELUM', 'SESUDAH'])
        ->and(array_column(OrderStatus::cases(), 'value'))->toBe([
            'MENUNGGU', 'DITUGASKAN', 'DIKONFIRMASI', 'DALAM_PERJALANAN',
            'DIKERJAKAN', 'SELESAI', 'LUNAS', 'DITOLAK', 'DIBATALKAN',
        ]);
});

it('punya label Bahasa Indonesia', function (UnitEnum $case, string $label) {
    expect($case->label())->toBe($label);
})->with([
    [UserRole::CLIENT, 'Client'],
    [UserRole::ADMIN, 'Admin'],
    [UserRole::TEKNISI, 'Teknisi'],
    [OrderType::REGULER, 'Reguler'],
    [OrderType::GARANSI, 'Garansi'],
    [PaymentMethod::TUNAI, 'Tunai'],
    [PaymentMethod::TRANSFER, 'Transfer'],
    [PhotoKind::KELUHAN, 'Keluhan'],
    [PhotoKind::SEBELUM, 'Sebelum'],
    [PhotoKind::SESUDAH, 'Sesudah'],
    [OrderStatus::MENUNGGU, 'Menunggu'],
    [OrderStatus::DITUGASKAN, 'Ditugaskan'],
    [OrderStatus::DIKONFIRMASI, 'Dikonfirmasi'],
    [OrderStatus::DALAM_PERJALANAN, 'Dalam Perjalanan'],
    [OrderStatus::DIKERJAKAN, 'Dikerjakan'],
    [OrderStatus::SELESAI, 'Selesai'],
    [OrderStatus::LUNAS, 'Lunas'],
    [OrderStatus::DITOLAK, 'Ditolak'],
    [OrderStatus::DIBATALKAN, 'Dibatalkan'],
]);

it('hanya DITOLAK dan DIBATALKAN yang tidak mengunci jadwal', function () {
    foreach (OrderStatus::cases() as $status) {
        $expected = ! in_array($status, [OrderStatus::DITOLAK, OrderStatus::DIBATALKAN], true);
        expect($status->locksSchedule())->toBe($expected, $status->value);
    }

    expect(OrderStatus::scheduleLockingValues())->toBe([
        'MENUNGGU', 'DITUGASKAN', 'DIKONFIRMASI', 'DALAM_PERJALANAN',
        'DIKERJAKAN', 'SELESAI', 'LUNAS',
    ]);
});

it('punya warna badge untuk setiap status', function () {
    foreach (OrderStatus::cases() as $status) {
        expect($status->color())->toBeString()->not->toBeEmpty();
    }
});
