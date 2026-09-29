<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan status order (state machine WORKFLOW bagian 3).
 *
 * Setiap transisi: validasi peta & pelaku, isi kolom waktu, tulis order_status_logs,
 * lalu pancarkan OrderStatusChanged setelah transaksi commit.
 */
class OrderStatusService
{
    /**
     * Transisi yang diizinkan: dari => [ke => peran yang boleh].
     *
     * @var array<string, array<string, list<UserRole>>>
     */
    private const TRANSITIONS = [
        'MENUNGGU' => [
            'DITUGASKAN' => [UserRole::ADMIN],
            'DITOLAK' => [UserRole::ADMIN],
            'DIBATALKAN' => [UserRole::CLIENT, UserRole::ADMIN],
        ],
        'DITUGASKAN' => [
            'DIKONFIRMASI' => [UserRole::TEKNISI],
            'MENUNGGU' => [UserRole::TEKNISI, UserRole::ADMIN],  // teknisi menolak / admin melepas teknisi
            'DITUGASKAN' => [UserRole::ADMIN],                   // admin ganti teknisi
            'DIBATALKAN' => [UserRole::CLIENT, UserRole::ADMIN],
        ],
        'DIKONFIRMASI' => [
            'DALAM_PERJALANAN' => [UserRole::TEKNISI],
            'DITUGASKAN' => [UserRole::ADMIN],                   // admin ganti teknisi
            'DIBATALKAN' => [UserRole::CLIENT, UserRole::ADMIN],
        ],
        'DALAM_PERJALANAN' => [
            'DIKERJAKAN' => [UserRole::TEKNISI],
        ],
        'DIKERJAKAN' => [
            'SELESAI' => [UserRole::TEKNISI],
        ],
        'SELESAI' => [
            'LUNAS' => [UserRole::ADMIN],
        ],
    ];

    /** Kolom waktu yang diisi saat masuk ke status tersebut. */
    private const TIMESTAMPS = [
        'DITUGASKAN' => 'assigned_at',
        'DIKONFIRMASI' => 'confirmed_at',
        'DALAM_PERJALANAN' => 'departed_at',
        'DIKERJAKAN' => 'started_at',
        'SELESAI' => 'completed_at',
        'LUNAS' => 'paid_at',
        'DITOLAK' => 'cancelled_at',
        'DIBATALKAN' => 'cancelled_at',
    ];

    /**
     * Catat status awal order baru (null → MENUNGGU). Panggil di dalam transaksi pembuatan order.
     */
    public function recordCreated(Order $order, ?User $actor = null, ?string $note = null): void
    {
        $this->log($order, null, $order->status, $actor, $note);

        OrderStatusChanged::dispatch($order, null, $order->status, $actor, $note);
    }

    /**
     * Ubah status order.
     *
     * @param  User|null  $actor  null = sistem (lewati pengecekan peran)
     * @param  array<string, mixed>  $attributes  kolom lain yang disimpan bersamaan (mis. technician_id saat assign)
     *
     * @throws InvalidStatusTransitionException
     * @throws AuthorizationException
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor, ?string $note = null, array $attributes = []): Order
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        return DB::transaction(function () use ($order, $to, $actor, $note, $attributes) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $from = $locked->status;

            $this->guard($locked, $from, $to, $actor, $note);

            $locked->fill($attributes);
            $locked->status = $to;
            $this->applySideEffects($locked, $from, $to, $note);

            if ($to === OrderStatus::DITUGASKAN && $locked->technician_id === null) {
                throw new InvalidStatusTransitionException('Pilih teknisi sebelum menugaskan order.');
            }

            $locked->save();
            $this->log($locked, $from, $to, $actor, $note);

            OrderStatusChanged::dispatch($locked, $from, $to, $actor, $note);

            $order->setRawAttributes($locked->getAttributes(), true);
            $order->unsetRelations();

            return $order;
        });
    }

    /**
     * Status tujuan yang boleh dipilih pelaku untuk order ini (untuk menampilkan tombol).
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(Order $order, User $actor): array
    {
        $allowed = [];

        foreach (array_keys(self::TRANSITIONS[$order->status->value] ?? []) as $to) {
            $target = OrderStatus::from($to);

            if ($this->actorMayTransition($order, $order->status, $target, $actor) && $this->withinCancellationWindow($order, $target, $actor)) {
                $allowed[] = $target;
            }
        }

        return $allowed;
    }

    /**
     * Client masih boleh membatalkan sendiri: ≥ cancel_limit_hours sebelum jadwal.
     */
    public function clientCanCancel(Order $order): bool
    {
        $hours = CompanySettings::current()->cancel_limit_hours;

        return isset(self::TRANSITIONS[$order->status->value][OrderStatus::DIBATALKAN->value])
            && CarbonImmutable::now()->lessThanOrEqualTo(CarbonImmutable::instance($order->scheduled_start)->subHours($hours));
    }

    /**
     * @throws InvalidStatusTransitionException
     * @throws AuthorizationException
     */
    private function guard(Order $order, OrderStatus $from, OrderStatus $to, ?User $actor, ?string $note): void
    {
        if (! isset(self::TRANSITIONS[$from->value][$to->value])) {
            throw new InvalidStatusTransitionException("Status order tidak bisa diubah dari {$from->label()} ke {$to->label()}.");
        }

        if ($actor !== null && ! $this->actorMayTransition($order, $from, $to, $actor)) {
            throw new AuthorizationException('Anda tidak berhak mengubah status order ini.');
        }

        if ($note === null && $this->requiresReason($from, $to)) {
            throw new InvalidStatusTransitionException('Alasan wajib diisi.');
        }

        if ($actor !== null && ! $this->withinCancellationWindow($order, $to, $actor)) {
            $hours = CompanySettings::current()->cancel_limit_hours;

            throw new InvalidStatusTransitionException(
                "Pembatalan hanya bisa paling lambat {$hours} jam sebelum jadwal. Silakan hubungi admin."
            );
        }

        if ($to === OrderStatus::DALAM_PERJALANAN && CarbonImmutable::instance($order->scheduled_start)->startOfDay()->isAfter(CarbonImmutable::today())) {
            throw new InvalidStatusTransitionException('Tombol Berangkat baru bisa dipakai pada hari jadwal.');
        }

        if ($to === OrderStatus::LUNAS && (int) $order->grand_total <= 0) {
            throw new InvalidStatusTransitionException('Order tanpa tagihan tidak perlu dikonfirmasi pembayarannya.');
        }
    }

    private function actorMayTransition(Order $order, OrderStatus $from, OrderStatus $to, User $actor): bool
    {
        $roles = self::TRANSITIONS[$from->value][$to->value] ?? [];

        if (! $actor->is_active || ! in_array($actor->role, $roles, true)) {
            return false;
        }

        return match ($actor->role) {
            UserRole::ADMIN => true,
            UserRole::CLIENT => $order->user_id === $actor->id,
            UserRole::TEKNISI => $order->technician_id !== null
                && $order->technician_id === $actor->technician?->id,
        };
    }

    private function withinCancellationWindow(Order $order, OrderStatus $to, User $actor): bool
    {
        if ($to !== OrderStatus::DIBATALKAN || $actor->role !== UserRole::CLIENT) {
            return true;
        }

        return $this->clientCanCancel($order);
    }

    private function requiresReason(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, [OrderStatus::DITOLAK, OrderStatus::DIBATALKAN], true)
            || ($from === OrderStatus::DITUGASKAN && $to === OrderStatus::MENUNGGU);
    }

    private function applySideEffects(Order $order, OrderStatus $from, OrderStatus $to, ?string $note): void
    {
        $now = now();

        if (isset(self::TIMESTAMPS[$to->value])) {
            $order->{self::TIMESTAMPS[$to->value]} = $now;
        }

        if (in_array($to, [OrderStatus::DITOLAK, OrderStatus::DIBATALKAN], true)) {
            $order->status_reason = $note;
        }

        // Kembali menunggu (teknisi menolak / dilepas admin): teknisi dikosongkan.
        if ($to === OrderStatus::MENUNGGU) {
            $order->technician_id = null;
            $order->assigned_at = null;
            $order->confirmed_at = null;
        }

        // Ganti teknisi: konfirmasi teknisi lama tidak berlaku lagi.
        if ($to === OrderStatus::DITUGASKAN && $from !== OrderStatus::MENUNGGU) {
            $order->confirmed_at = null;
        }
    }

    private function log(Order $order, ?OrderStatus $from, OrderStatus $to, ?User $actor, ?string $note): void
    {
        $order->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'note' => $note,
        ]);
    }
}
