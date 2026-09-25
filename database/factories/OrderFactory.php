<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\CustomerAddress;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Default: order REGULER berstatus MENUNGGU, jadwal 1–14 hari ke depan, alamat milik client.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** Buffer perjalanan default (menit) — sama dengan default company_settings. */
    private const TRAVEL_BUFFER = 30;

    /** Urutan status normal, untuk mengisi kolom waktu sampai status tujuan. */
    private const FLOW = [
        OrderStatus::MENUNGGU,
        OrderStatus::DITUGASKAN,
        OrderStatus::DIKONFIRMASI,
        OrderStatus::DALAM_PERJALANAN,
        OrderStatus::DIKERJAKAN,
        OrderStatus::SELESAI,
        OrderStatus::LUNAS,
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('+1 day', '+14 days'))
            ->setTime(fake()->numberBetween(8, 14), 0);
        $priceMin = fake()->numberBetween(15, 60) * 5000;

        return [
            // Nomor 5000+ agar tidak bentrok dengan OrderCodeGenerator (mulai 0001) di test.
            'code' => sprintf('ORD-%s-%04d', now()->format('ym'), fake()->unique()->numberBetween(5000, 9999)),
            'order_type' => OrderType::REGULER,
            'parent_order_id' => null,
            'user_id' => User::factory(),
            'customer_address_id' => fn (array $attributes) => CustomerAddress::factory()
                ->create(['user_id' => $attributes['user_id']])
                ->getKey(),
            'address_snapshot' => fn (array $attributes) => $this->addressSnapshot($attributes),
            'lat' => fn (array $attributes) => $this->address($attributes)->lat,
            'lng' => fn (array $attributes) => $this->address($attributes)->lng,
            'distance_km' => fn (array $attributes) => $this->address($attributes)->distance_km,
            'technician_id' => null,
            'helper_id' => null,
            'requires_helper' => false,
            'scheduled_start' => $start,
            'total_est_minutes' => fake()->randomElement([60, 90, 120, 150]),
            'scheduled_end' => fn (array $attributes) => Carbon::parse($attributes['scheduled_start'])
                ->addMinutes($attributes['total_est_minutes']),
            'blocked_until' => fn (array $attributes) => Carbon::parse($attributes['scheduled_end'])
                ->addMinutes(self::TRAVEL_BUFFER),
            'est_price_min' => $priceMin,
            'est_price_max' => $priceMin + fake()->numberBetween(1, 8) * 5000,
            'transport_fee' => 0,
            'final_price' => null,
            'grand_total' => null,
            'customer_note' => fake()->optional()->randomElement([
                'AC kurang dingin', 'AC menetes air', 'Bunyi berisik saat menyala', 'Cuci rutin',
            ]),
            'status' => OrderStatus::MENUNGGU,
        ];
    }

    /**
     * Atur jadwal mulai (dan durasi); scheduled_end & blocked_until mengikuti.
     */
    public function at(DateTimeInterface|string $start, ?int $minutes = null): static
    {
        return $this->state(fn () => array_filter([
            'scheduled_start' => Carbon::parse($start),
            'total_est_minutes' => $minutes,
        ], fn ($value) => $value !== null));
    }

    /**
     * Isi status beserta kolom yang semestinya terisi sampai status tersebut
     * (teknisi, waktu tiap tahap, harga final, pembayaran).
     */
    public function withStatus(OrderStatus $status): static
    {
        return $this->state(function (array $attributes) use ($status) {
            if (! $status->locksSchedule()) {
                return [
                    'status' => $status,
                    'cancelled_at' => now(),
                    'status_reason' => $status === OrderStatus::DITOLAK
                        ? 'Jadwal penuh, mohon pilih tanggal lain.'
                        : 'Client berhalangan.',
                ];
            }

            $reached = array_search($status, self::FLOW, true);
            $state = ['status' => $status];

            if ($reached >= 1) {
                $state['technician_id'] = $attributes['technician_id'] ?? Technician::factory();
                $state['assigned_at'] = now();
            }
            if ($reached >= 2) {
                $state['confirmed_at'] = now();
            }
            if ($reached >= 3) {
                $state['departed_at'] = fn (array $a) => Carbon::parse($a['scheduled_start'])->subMinutes(30);
            }
            if ($reached >= 4) {
                $state['started_at'] = fn (array $a) => Carbon::parse($a['scheduled_start']);
            }
            if ($reached >= 5) {
                $state['completed_at'] = fn (array $a) => Carbon::parse($a['scheduled_end']);
                $state['final_price'] = fn (array $a) => $a['est_price_max'];
                $state['grand_total'] = fn (array $a) => $a['est_price_max'] + $a['transport_fee'];
            }
            if ($reached >= 6) {
                $state['payment_method'] = PaymentMethod::TUNAI;
                $state['paid_at'] = fn (array $a) => Carbon::parse($a['scheduled_end'])->addHour();
            }

            return $state;
        });
    }

    public function withHelper(): static
    {
        return $this->state(fn () => [
            'helper_id' => Helper::factory(),
            'requires_helper' => true,
        ]);
    }

    /**
     * Order GARANSI dari order asal (Rp0, alamat & client sama).
     */
    public function warranty(Order $parent): static
    {
        return $this->state(fn () => [
            'order_type' => OrderType::GARANSI,
            'parent_order_id' => $parent->getKey(),
            'user_id' => $parent->user_id,
            'customer_address_id' => $parent->customer_address_id,
            'est_price_min' => 0,
            'est_price_max' => 0,
            'transport_fee' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function address(array $attributes): CustomerAddress
    {
        return CustomerAddress::withTrashed()->findOrFail($attributes['customer_address_id']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addressSnapshot(array $attributes): string
    {
        $address = $this->address($attributes);

        return $address->landmark ? "{$address->address} (Patokan: {$address->landmark})" : $address->address;
    }
}
