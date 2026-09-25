<?php

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use App\Services\OrderStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00'));
    CompanySettings::factory()->create(['cancel_limit_hours' => 3]);

    $this->service = new OrderStatusService;
    $this->admin = User::factory()->admin()->create();
    $this->technician = Technician::factory()->create();
    $this->client = User::factory()->create();
});

/**
 * Order milik client uji, dijadwalkan besok jam 09:00, pada status tertentu.
 */
function orderIn(OrderStatus $status, array $attributes = []): Order
{
    $test = test();

    return Order::factory()
        ->at(CarbonImmutable::parse('2026-10-06 09:00'), 60)
        ->withStatus($status)
        ->create(array_merge([
            'user_id' => $test->client->id,
            'technician_id' => $status === OrderStatus::MENUNGGU ? null : $test->technician->id,
        ], $attributes));
}

describe('alur normal', function () {
    it('menjalankan order dari MENUNGGU sampai LUNAS dan mencatat semuanya', function () {
        Event::fake([OrderStatusChanged::class]);
        $order = orderIn(OrderStatus::MENUNGGU);
        $techUser = $this->technician->user;

        $this->service->transition($order, OrderStatus::DITUGASKAN, $this->admin, attributes: ['technician_id' => $this->technician->id]);
        $this->service->transition($order, OrderStatus::DIKONFIRMASI, $techUser);
        $this->service->transition($order, OrderStatus::DALAM_PERJALANAN, $techUser);
        $this->service->transition($order, OrderStatus::DIKERJAKAN, $techUser);
        $this->service->transition($order, OrderStatus::SELESAI, $techUser, attributes: ['final_price' => 90000, 'grand_total' => 90000]);
        $this->service->transition($order, OrderStatus::LUNAS, $this->admin, attributes: ['paid_confirmed_by' => $this->admin->id]);

        $order->refresh();

        expect($order->status)->toBe(OrderStatus::LUNAS)
            ->and($order->technician_id)->toBe($this->technician->id)
            ->and($order->grand_total)->toBe(90000)
            ->and($order->paid_confirmed_by)->toBe($this->admin->id);

        foreach (['assigned_at', 'confirmed_at', 'departed_at', 'started_at', 'completed_at', 'paid_at'] as $column) {
            expect($order->{$column}?->toDateTimeString())->toBe('2026-10-05 07:00:00', $column);
        }

        $logs = $order->statusLogs;
        expect($logs->map(fn ($log) => [$log->from_status?->value, $log->to_status->value, $log->changed_by])->all())->toBe([
            ['MENUNGGU', 'DITUGASKAN', $this->admin->id],
            ['DITUGASKAN', 'DIKONFIRMASI', $techUser->id],
            ['DIKONFIRMASI', 'DALAM_PERJALANAN', $techUser->id],
            ['DALAM_PERJALANAN', 'DIKERJAKAN', $techUser->id],
            ['DIKERJAKAN', 'SELESAI', $techUser->id],
            ['SELESAI', 'LUNAS', $this->admin->id],
        ]);

        Event::assertDispatchedTimes(OrderStatusChanged::class, 6);
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->from === OrderStatus::SELESAI
            && $event->to === OrderStatus::LUNAS
            && $event->actor->is($this->admin)
            && $event->order->is($order));
    });

    it('memperbarui instance order yang diberikan', function () {
        $order = orderIn(OrderStatus::DITUGASKAN);

        $returned = $this->service->transition($order, OrderStatus::DIKONFIRMASI, $this->technician->user);

        expect($returned)->toBe($order)
            ->and($order->status)->toBe(OrderStatus::DIKONFIRMASI)
            ->and($order->isDirty())->toBeFalse();
    });

    it('mencatat order baru (null → MENUNGGU) dan memancarkan event', function () {
        Event::fake([OrderStatusChanged::class]);
        $order = orderIn(OrderStatus::MENUNGGU);

        $this->service->recordCreated($order, $this->client);

        $log = $order->statusLogs()->sole();
        expect($log->from_status)->toBeNull()
            ->and($log->to_status)->toBe(OrderStatus::MENUNGGU)
            ->and($log->changed_by)->toBe($this->client->id);
        Event::assertDispatched(OrderStatusChanged::class, fn ($event) => $event->from === null && $event->to === OrderStatus::MENUNGGU);
    });
});

describe('transisi tidak valid', function () {
    it('menolak lompatan status di luar state machine', function (OrderStatus $from, OrderStatus $to) {
        $order = orderIn($from);

        $this->service->transition($order, $to, null, 'alasan');
    })->with([
        'MENUNGGU → SELESAI' => [OrderStatus::MENUNGGU, OrderStatus::SELESAI],
        'MENUNGGU → DIKONFIRMASI' => [OrderStatus::MENUNGGU, OrderStatus::DIKONFIRMASI],
        'DITUGASKAN → DIKERJAKAN' => [OrderStatus::DITUGASKAN, OrderStatus::DIKERJAKAN],
        'DALAM_PERJALANAN → DIBATALKAN' => [OrderStatus::DALAM_PERJALANAN, OrderStatus::DIBATALKAN],
        'DIKERJAKAN → DIBATALKAN' => [OrderStatus::DIKERJAKAN, OrderStatus::DIBATALKAN],
        'SELESAI → DIKERJAKAN' => [OrderStatus::SELESAI, OrderStatus::DIKERJAKAN],
        'LUNAS → SELESAI' => [OrderStatus::LUNAS, OrderStatus::SELESAI],
        'DITOLAK → MENUNGGU' => [OrderStatus::DITOLAK, OrderStatus::MENUNGGU],
        'DIBATALKAN → DITUGASKAN' => [OrderStatus::DIBATALKAN, OrderStatus::DITUGASKAN],
    ])->throws(InvalidStatusTransitionException::class, 'Status order tidak bisa diubah dari');

    it('tidak mengubah apa pun bila transisi ditolak', function () {
        $order = orderIn(OrderStatus::MENUNGGU);

        try {
            $this->service->transition($order, OrderStatus::SELESAI, $this->admin);
        } catch (InvalidStatusTransitionException) {
        }

        expect($order->fresh()->status)->toBe(OrderStatus::MENUNGGU)
            ->and($order->statusLogs()->count())->toBe(0);
    });

    it('memeriksa status terbaru di database, bukan instance lama', function () {
        $order = orderIn(OrderStatus::DITUGASKAN);
        $stale = Order::find($order->id);

        $this->service->transition($order, OrderStatus::DIKONFIRMASI, $this->technician->user);

        // Instance lama masih DITUGASKAN, tapi di DB sudah DIKONFIRMASI.
        $this->service->transition($stale, OrderStatus::DIKONFIRMASI, $this->technician->user);
    })->throws(InvalidStatusTransitionException::class);

    it('mewajibkan teknisi saat menugaskan', function () {
        $this->service->transition(orderIn(OrderStatus::MENUNGGU), OrderStatus::DITUGASKAN, $this->admin);
    })->throws(InvalidStatusTransitionException::class, 'Pilih teknisi sebelum menugaskan order.');

    it('menolak LUNAS untuk order tanpa tagihan (garansi Rp0)', function () {
        $order = orderIn(OrderStatus::SELESAI, ['est_price_max' => 0, 'final_price' => 0, 'grand_total' => 0]);

        $this->service->transition($order, OrderStatus::LUNAS, $this->admin);
    })->throws(InvalidStatusTransitionException::class, 'Order tanpa tagihan');
});

describe('hak pelaku', function () {
    it('hanya mengizinkan peran yang sesuai', function (OrderStatus $from, OrderStatus $to, string $actor) {
        $user = match ($actor) {
            'admin' => $this->admin,
            'teknisi' => $this->technician->user,
            'client' => $this->client,
        };

        $this->service->transition(orderIn($from), $to, $user, 'alasan');
    })->with([
        'client menugaskan' => [OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, 'client'],
        'teknisi menolak order baru' => [OrderStatus::MENUNGGU, OrderStatus::DITOLAK, 'teknisi'],
        'admin mengonfirmasi atas nama teknisi' => [OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI, 'admin'],
        'client menandai berangkat' => [OrderStatus::DIKONFIRMASI, OrderStatus::DALAM_PERJALANAN, 'client'],
        'teknisi menandai lunas' => [OrderStatus::SELESAI, OrderStatus::LUNAS, 'teknisi'],
        'teknisi membatalkan' => [OrderStatus::DIKONFIRMASI, OrderStatus::DIBATALKAN, 'teknisi'],
    ])->throws(AuthorizationException::class);

    it('menolak client lain membatalkan order yang bukan miliknya', function () {
        $this->service->transition(orderIn(OrderStatus::MENUNGGU), OrderStatus::DIBATALKAN, User::factory()->create(), 'batal');
    })->throws(AuthorizationException::class);

    it('menolak teknisi lain mengubah order yang bukan tugasnya', function () {
        $other = Technician::factory()->create();

        $this->service->transition(orderIn(OrderStatus::DITUGASKAN), OrderStatus::DIKONFIRMASI, $other->user);
    })->throws(AuthorizationException::class);

    it('menolak pelaku yang akunnya nonaktif', function () {
        $this->admin->update(['is_active' => false]);

        $this->service->transition(orderIn(OrderStatus::MENUNGGU), OrderStatus::DITOLAK, $this->admin, 'penuh');
    })->throws(AuthorizationException::class);

    it('memberi daftar tombol status sesuai pelaku', function () {
        $order = orderIn(OrderStatus::DITUGASKAN);

        expect($this->service->allowedTransitions($order, $this->technician->user))->toBe([OrderStatus::DIKONFIRMASI, OrderStatus::MENUNGGU])
            ->and($this->service->allowedTransitions($order, $this->admin))->toBe([OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, OrderStatus::DIBATALKAN])
            ->and($this->service->allowedTransitions($order, $this->client))->toBe([OrderStatus::DIBATALKAN])
            ->and($this->service->allowedTransitions($order, User::factory()->create()))->toBe([]);
    });
});

describe('penolakan, pembatalan & penggantian teknisi', function () {
    it('mewajibkan alasan untuk tolak & batal', function (OrderStatus $from, OrderStatus $to, string $actor) {
        $user = $actor === 'admin' ? $this->admin : $this->technician->user;

        $this->service->transition(orderIn($from), $to, $user, '   ');
    })->with([
        'admin tolak' => [OrderStatus::MENUNGGU, OrderStatus::DITOLAK, 'admin'],
        'admin batal' => [OrderStatus::DIKONFIRMASI, OrderStatus::DIBATALKAN, 'admin'],
        'teknisi tolak tugas' => [OrderStatus::DITUGASKAN, OrderStatus::MENUNGGU, 'teknisi'],
    ])->throws(InvalidStatusTransitionException::class, 'Alasan wajib diisi.');

    it('menyimpan alasan penolakan admin', function () {
        $order = orderIn(OrderStatus::MENUNGGU);

        $this->service->transition($order, OrderStatus::DITOLAK, $this->admin, 'Jadwal penuh');

        expect($order->status)->toBe(OrderStatus::DITOLAK)
            ->and($order->status_reason)->toBe('Jadwal penuh')
            ->and($order->cancelled_at)->not->toBeNull()
            ->and($order->statusLogs()->sole()->note)->toBe('Jadwal penuh');
    });

    it('mengosongkan teknisi saat teknisi menolak tugas', function () {
        $order = orderIn(OrderStatus::DITUGASKAN);

        $this->service->transition($order, OrderStatus::MENUNGGU, $this->technician->user, 'Sedang sakit');

        expect($order->status)->toBe(OrderStatus::MENUNGGU)
            ->and($order->technician_id)->toBeNull()
            ->and($order->assigned_at)->toBeNull()
            ->and($order->statusLogs()->sole()->note)->toBe('Sedang sakit');
    });

    it('mengizinkan admin mengganti teknisi (kembali DITUGASKAN, konfirmasi direset)', function () {
        $order = orderIn(OrderStatus::DIKONFIRMASI);
        $replacement = Technician::factory()->create();

        $this->service->transition($order, OrderStatus::DITUGASKAN, $this->admin, 'Ganti dari teknisi lama', ['technician_id' => $replacement->id]);

        $log = $order->statusLogs()->sole();
        expect($order->status)->toBe(OrderStatus::DITUGASKAN)
            ->and($order->technician_id)->toBe($replacement->id)
            ->and($order->confirmed_at)->toBeNull()
            ->and([$log->from_status, $log->to_status])->toBe([OrderStatus::DIKONFIRMASI, OrderStatus::DITUGASKAN]);
    });

    it('mengizinkan client membatalkan paling lambat 3 jam sebelum jadwal', function () {
        $order = orderIn(OrderStatus::DIKONFIRMASI);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 06:00')); // tepat 3 jam sebelum 09:00

        expect($this->service->clientCanCancel($order))->toBeTrue();

        $this->service->transition($order, OrderStatus::DIBATALKAN, $this->client, 'Ada acara');

        expect($order->status)->toBe(OrderStatus::DIBATALKAN)
            ->and($order->status_reason)->toBe('Ada acara');
    });

    it('menolak client membatalkan 2 jam sebelum jadwal', function () {
        $order = orderIn(OrderStatus::DITUGASKAN);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00'));

        expect($this->service->clientCanCancel($order))->toBeFalse()
            ->and($this->service->allowedTransitions($order, $this->client))->toBe([]);

        $this->service->transition($order, OrderStatus::DIBATALKAN, $this->client, 'Ada acara');
    })->throws(InvalidStatusTransitionException::class, 'Pembatalan hanya bisa paling lambat 3 jam sebelum jadwal.');

    it('mengikuti batas batal dari pengaturan', function () {
        CompanySettings::current()->update(['cancel_limit_hours' => 1]);
        $order = orderIn(OrderStatus::MENUNGGU);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 07:30'));

        expect($this->service->clientCanCancel($order))->toBeTrue();
    });

    it('tetap mengizinkan admin membatalkan mendekati jadwal', function () {
        $order = orderIn(OrderStatus::DIKONFIRMASI);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:30'));

        $this->service->transition($order, OrderStatus::DIBATALKAN, $this->admin, 'Client minta via telepon');

        expect($order->status)->toBe(OrderStatus::DIBATALKAN);
    });
});
