<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\AcBrand;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Helper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use App\Services\DistanceService;
use App\Services\OrderCodeGenerator;
use App\Services\TransportFeeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data demo untuk pengembangan lokal: 4 teknisi, 2 helper, 5 client, 20 order berbagai status.
 * Semua akun demo memakai password "password".
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** Urutan status normal untuk mengisi kolom waktu & log. */
    private const FLOW = [
        OrderStatus::MENUNGGU,
        OrderStatus::DITUGASKAN,
        OrderStatus::DIKONFIRMASI,
        OrderStatus::DALAM_PERJALANAN,
        OrderStatus::DIKERJAKAN,
        OrderStatus::SELESAI,
        OrderStatus::LUNAS,
    ];

    private CompanySettings $settings;

    private User $admin;

    /** @var list<Technician> */
    private array $technicians = [];

    /** @var list<Helper> */
    private array $helpers = [];

    /** @var list<User> */
    private array $clients = [];

    /** @var Collection<string, Service> */
    private Collection $services;

    /** @var array<int, list<ServicePrice>> harga aktif per service_id */
    private array $prices = [];

    /** @var list<string> */
    private array $brands;

    /** @var array<string, CarbonImmutable> */
    private array $days = [];

    public function run(): void
    {
        if (User::query()->where('email', 'rudi@booking-ac.test')->exists()) {
            $this->command->warn('Data demo sudah ada — dilewati.');

            return;
        }

        fake()->seed(20260925);

        $this->settings = CompanySettings::current();
        $this->admin = User::query()->where('role', UserRole::ADMIN)->firstOrFail();
        $this->services = Service::query()->get()->keyBy('slug');
        foreach (ServicePrice::query()->where('is_active', true)->orderBy('id')->get() as $price) {
            $this->prices[$price->service_id][] = $price;
        }
        $this->brands = AcBrand::query()->pluck('id')->all();
        $this->days = $this->workdays();

        DB::transaction(function () {
            $this->seedTeam();
            $this->seedClients();
            $this->seedOrders();
            $this->seedLeaves();
            $this->refreshTechnicianRatings();
        });
    }

    private function seedTeam(): void
    {
        $technicians = [
            ['Rudi Setiawan', 'rudi', '081311110001'],
            ['Ahmad Fauzi', 'ahmad', '081311110002'],
            ['Dimas Saputra', 'dimas', '081311110003'],
            ['Yoga Pratama', 'yoga', '081311110004'],
        ];

        foreach ($technicians as [$name, $slug, $phone]) {
            $user = User::create([
                'name' => $name,
                'email' => "{$slug}@booking-ac.test",
                'phone' => $phone,
                'role' => UserRole::TEKNISI,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ]);

            $this->technicians[] = Technician::create(['user_id' => $user->id]);
        }

        $this->helpers[] = Helper::create(['name' => 'Agus Salim', 'phone' => '081322220001']);
        $this->helpers[] = Helper::create(['name' => 'Bayu Hidayat', 'phone' => '081322220002']);
    }

    private function seedClients(): void
    {
        $clients = [
            ['Dena Rizki', 'dena.rizki@example.com', '081255550001', [
                ['Rumah', 'Jl. Tebet Raya No. 12, Tebet, Jakarta Selatan', 'Seberang minimarket', -6.2267000, 106.8530000],
                ['Kantor', 'Jl. Jend. Sudirman Kav. 52, Senayan, Jakarta Selatan', 'Gedung lantai 8', -6.2253000, 106.8090000],
            ]],
            ['Budi Santoso', 'budi.santoso@example.com', '081255550002', [
                ['Rumah', 'Jl. RS Fatmawati Raya No. 45, Cilandak, Jakarta Selatan', 'Pagar hitam', -6.2915000, 106.7970000],
            ]],
            ['Siti Rahmawati', 'siti.rahma@example.com', '081255550003', [
                ['Rumah', 'Jl. Margonda Raya No. 100, Beji, Depok', 'Dekat kampus', -6.3690000, 106.8320000],
            ]],
            ['Andi Pratama', 'andi.pratama@example.com', '081255550004', [
                ['Rumah', 'Jl. Metro Pondok Indah Blok B No. 7, Jakarta Selatan', null, -6.2650000, 106.7830000],
            ]],
            ['Rina Wijaya', 'rina.wijaya@example.com', '081255550005', [
                ['Rumah', 'Jl. Kalimalang No. 21, Duren Sawit, Jakarta Timur', 'Samping masjid', -6.2390000, 106.9150000],
            ]],
        ];

        foreach ($clients as [$name, $email, $phone, $addresses]) {
            $client = User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => (string) fake()->unique()->numerify('1##################'),
                'phone' => $phone,
                'role' => UserRole::CLIENT,
                'email_verified_at' => now(),
            ]);

            foreach ($addresses as $index => [$label, $address, $landmark, $lat, $lng]) {
                CustomerAddress::create([
                    'user_id' => $client->id,
                    'label' => $label,
                    'address' => $address,
                    'landmark' => $landmark,
                    'lat' => $lat,
                    'lng' => $lng,
                    'distance_km' => $this->distanceKm($lat, $lng),
                    'is_default' => $index === 0,
                ]);
            }

            $this->clients[] = $client;
        }
    }

    private function seedOrders(): void
    {
        // [hari, jam, teknisi, status, layanan per unit, helper, client, rating ulasan]
        $specs = [
            ['P6', '09:00', 0, OrderStatus::LUNAS, ['cuci-ac', 'cuci-ac'], null, 0, 5],
            ['P6', '13:00', 1, OrderStatus::LUNAS, ['perbaikan'], null, 1, 4],
            ['P5', '09:00', 2, OrderStatus::LUNAS, ['instalasi'], 0, 2, 4],
            ['P5', '10:00', 3, OrderStatus::LUNAS, ['cuci-ac', 'cuci-ac', 'cuci-ac'], null, 3, null],
            ['P4', '09:00', 0, OrderStatus::SELESAI, ['isi-freon'], null, 4, null],
            ['P4', '13:00', 1, OrderStatus::DIBATALKAN, ['cuci-ac'], null, 0, null],
            ['P3', '09:00', 2, OrderStatus::LUNAS, ['cuci-ac', 'cuci-ac'], null, 1, 5],
            ['P3', '14:00', null, OrderStatus::DITOLAK, ['perbaikan'], null, 2, null],
            ['P2', '09:00', 0, OrderStatus::SELESAI, ['cuci-ac', 'cuci-ac'], null, 0, null],
            ['P1', '10:00', 1, OrderStatus::SELESAI, ['perbaikan'], null, 3, 5],
            ['D0', '08:00', 0, OrderStatus::DIKERJAKAN, ['cuci-ac', 'cuci-ac'], null, 1, null],
            ['D0', '10:00', 1, OrderStatus::DALAM_PERJALANAN, ['isi-freon'], null, 4, null],
            ['D0', '13:00', 2, OrderStatus::DIKONFIRMASI, ['instalasi'], 1, 0, null],
            ['D0', '14:00', 3, OrderStatus::DITUGASKAN, ['cuci-ac'], null, 2, null],
            ['F1', '09:00', null, OrderStatus::MENUNGGU, ['cuci-ac', 'cuci-ac'], null, 3, null],
            ['F1', '13:00', null, OrderStatus::MENUNGGU, ['instalasi'], null, 4, null],
            ['F2', '10:00', 0, OrderStatus::DITUGASKAN, ['perbaikan'], null, 1, null],
            ['F2', '09:00', 1, OrderStatus::DIKONFIRMASI, ['cuci-ac', 'cuci-ac', 'cuci-ac'], null, 2, null],
            ['F4', '09:00', null, OrderStatus::MENUNGGU, ['isi-freon'], null, 0, null],
        ];

        $orders = [];
        foreach ($specs as [$day, $time, $tech, $status, $units, $helper, $client, $rating]) {
            $order = $this->createOrder(
                start: $this->days[$day]->setTimeFromTimeString($time),
                client: $this->clients[$client],
                status: $status,
                units: $units,
                technician: $tech !== null ? $this->technicians[$tech] : null,
                helper: $helper !== null ? $this->helpers[$helper] : null,
            );

            if ($rating !== null) {
                Review::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'technician_id' => $order->technician_id,
                    'rating' => $rating,
                    'comment' => fake()->randomElement([
                        'Teknisi ramah dan cepat.', 'AC kembali dingin, terima kasih!', 'Datang tepat waktu, kerja rapi.',
                    ]),
                ]);
            }

            $orders[] = $order;
        }

        // Klaim garansi atas order P2 (masih dalam masa garansi).
        $this->createWarrantyClaim($orders[8], $this->days['F3']->setTime(11, 0));
    }

    /**
     * @param  list<string>  $units  slug layanan per unit
     */
    private function createOrder(
        CarbonImmutable $start,
        User $client,
        OrderStatus $status,
        array $units,
        ?Technician $technician = null,
        ?Helper $helper = null,
    ): Order {
        /** @var CustomerAddress $address */
        $address = $client->addresses()->orderByDesc('is_default')->firstOrFail();

        $lines = array_map(function (string $slug) {
            $service = $this->services[$slug];
            /** @var ServicePrice $price */
            $price = fake()->randomElement($this->prices[$service->id]);

            return [$service, $price, fake()->randomElement($this->brands)];
        }, $units);

        $minutes = array_sum(array_map(fn (array $line) => $line[1]->est_minutes, $lines));
        $end = $start->addMinutes($minutes);
        $createdAt = $this->createdAt($start);
        $transportFee = $this->transportFee((float) $address->distance_km);

        $order = new Order([
            'code' => $this->nextCode($createdAt),
            'order_type' => OrderType::REGULER,
            'user_id' => $client->id,
            'customer_address_id' => $address->id,
            'address_snapshot' => $address->landmark ? "{$address->address} (Patokan: {$address->landmark})" : $address->address,
            'lat' => $address->lat,
            'lng' => $address->lng,
            'distance_km' => $address->distance_km,
            'technician_id' => $technician?->id,
            'helper_id' => $helper?->id,
            'requires_helper' => collect($lines)->contains(fn (array $line) => $line[0]->requires_helper),
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'blocked_until' => $end->addMinutes($this->settings->travel_buffer_minutes),
            'total_est_minutes' => $minutes,
            'est_price_min' => array_sum(array_map(fn (array $line) => $line[1]->price_min, $lines)),
            'est_price_max' => array_sum(array_map(fn (array $line) => $line[1]->price_max, $lines)),
            'transport_fee' => $transportFee,
            'customer_note' => fake()->optional(0.6)->randomElement([
                'AC kamar kurang dingin.', 'Ada air menetes dari unit indoor.', 'Bunyi berisik saat menyala.', 'Cuci rutin 3 bulanan.',
            ]),
            'status' => $status,
        ]);
        $order->created_at = $createdAt->toMutable();
        $this->fillStatusTimestamps($order, $status, $createdAt, $start, $end);
        $order->save();

        $completed = in_array($status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true);
        $finalTotal = 0;

        foreach ($lines as $index => [$service, $price, $brandId]) {
            $item = $this->makeItem($order, $service, $price, $brandId, $index);

            if ($completed) {
                $overBudget = $service->slug === 'perbaikan';
                $item->final_price = $overBudget
                    ? $price->price_max + 75000
                    : fake()->numberBetween($price->price_min / 5000, $price->price_max / 5000) * 5000;
                $item->price_note = $overBudget ? 'Ganti kapasitor outdoor 35µF.' : null;
                $item->work_note = fake()->optional(0.5)->randomElement(['Filter sangat kotor, sudah dibersihkan.', 'Tekanan freon normal.']);
                $item->warranty_until = $end->startOfDay()->addDays($item->warranty_days)->toMutable();
                $finalTotal += $item->final_price;
            }

            $item->save();
        }

        if ($completed) {
            $order->update([
                'final_price' => $finalTotal,
                'grand_total' => $finalTotal + $transportFee,
                'technician_note' => 'Semua unit sudah dicek dan berfungsi normal.',
            ]);
        }

        $this->writeStatusLogs($order, $status, $client);

        return $order;
    }

    private function createWarrantyClaim(Order $parent, CarbonImmutable $start): void
    {
        /** @var OrderItem $claimed */
        $claimed = $parent->items()->firstOrFail();
        $end = $start->addMinutes($claimed->est_minutes);
        $createdAt = $this->createdAt($start);

        $order = new Order([
            'code' => $this->nextCode($createdAt),
            'order_type' => OrderType::GARANSI,
            'parent_order_id' => $parent->id,
            'user_id' => $parent->user_id,
            'customer_address_id' => $parent->customer_address_id,
            'address_snapshot' => $parent->address_snapshot,
            'lat' => $parent->lat,
            'lng' => $parent->lng,
            'distance_km' => $parent->distance_km,
            'requires_helper' => false,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'blocked_until' => $end->addMinutes($this->settings->travel_buffer_minutes),
            'total_est_minutes' => $claimed->est_minutes,
            'est_price_min' => 0,
            'est_price_max' => 0,
            'transport_fee' => 0,
            'customer_note' => 'AC kembali menetes air setelah dicuci.',
            'status' => OrderStatus::MENUNGGU,
        ]);
        $order->created_at = $createdAt->toMutable();
        $order->save();

        $order->items()->create([
            'claimed_item_id' => $claimed->id,
            'service_id' => $claimed->service_id,
            'service_price_id' => $claimed->service_price_id,
            'ac_brand_id' => $claimed->ac_brand_id,
            'ac_capacity_id' => $claimed->ac_capacity_id,
            'ac_type_id' => $claimed->ac_type_id,
            'service_name' => $claimed->service_name,
            'unit_label' => $claimed->unit_label,
            'est_price_min' => 0,
            'est_price_max' => 0,
            'est_minutes' => $claimed->est_minutes,
            'warranty_days' => $claimed->warranty_days,
            'sort_order' => 1,
        ]);

        $this->writeStatusLogs($order, OrderStatus::MENUNGGU, $parent->client);
    }

    private function makeItem(Order $order, Service $service, ServicePrice $price, int $brandId, int $index): OrderItem
    {
        $price->loadMissing(['capacity', 'type']);
        $brand = AcBrand::query()->findOrFail($brandId);

        return new OrderItem([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'service_price_id' => $price->id,
            'ac_brand_id' => $brandId,
            'ac_capacity_id' => $price->ac_capacity_id,
            'ac_type_id' => $price->ac_type_id,
            'service_name' => $service->name,
            'unit_label' => "{$brand->name} · {$price->capacity->label} · {$price->type->name}",
            'est_price_min' => $price->price_min,
            'est_price_max' => $price->price_max,
            'est_minutes' => $price->est_minutes,
            'warranty_days' => $service->warranty_days ?? $this->settings->warranty_days,
            'sort_order' => $index + 1,
        ]);
    }

    private function fillStatusTimestamps(Order $order, OrderStatus $status, CarbonImmutable $createdAt, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($status === OrderStatus::DITOLAK) {
            $order->cancelled_at = $createdAt->addHour()->toMutable();
            $order->status_reason = 'Jadwal teknisi penuh di jam tersebut, mohon pilih jam lain.';

            return;
        }

        if ($status === OrderStatus::DIBATALKAN) {
            $order->assigned_at = $createdAt->addHours(2)->toMutable();
            $order->cancelled_at = $createdAt->addHours(5)->toMutable();
            $order->status_reason = 'Client berhalangan di rumah.';

            return;
        }

        $reached = (int) array_search($status, self::FLOW, true);
        $assignedAt = $createdAt->addHours(2);

        $timestamps = [
            1 => ['assigned_at', $assignedAt],
            2 => ['confirmed_at', $assignedAt->addHour()],
            3 => ['departed_at', $start->subMinutes(30)],
            4 => ['started_at', $start],
            5 => ['completed_at', $end],
            6 => ['paid_at', $end->addHour()],
        ];

        foreach ($timestamps as $rank => [$column, $value]) {
            if ($reached >= $rank) {
                $order->{$column} = $value->toMutable();
            }
        }

        if ($reached >= 6) {
            $order->payment_method = fake()->randomElement(PaymentMethod::cases());
            $order->paid_confirmed_by = $this->admin->id;
        }
    }

    /**
     * Log status dari MENUNGGU sampai status akhir, dengan pelaku yang sesuai.
     */
    private function writeStatusLogs(Order $order, OrderStatus $status, User $client): void
    {
        $technicianUserId = $order->technician?->user_id;

        $steps = match ($status) {
            OrderStatus::DITOLAK => [
                [OrderStatus::MENUNGGU, $client->id, $order->created_at],
                [OrderStatus::DITOLAK, $this->admin->id, $order->cancelled_at],
            ],
            OrderStatus::DIBATALKAN => [
                [OrderStatus::MENUNGGU, $client->id, $order->created_at],
                [OrderStatus::DITUGASKAN, $this->admin->id, $order->assigned_at],
                [OrderStatus::DIBATALKAN, $client->id, $order->cancelled_at],
            ],
            default => array_slice([
                [OrderStatus::MENUNGGU, $client->id, $order->created_at],
                [OrderStatus::DITUGASKAN, $this->admin->id, $order->assigned_at],
                [OrderStatus::DIKONFIRMASI, $technicianUserId, $order->confirmed_at],
                [OrderStatus::DALAM_PERJALANAN, $technicianUserId, $order->departed_at],
                [OrderStatus::DIKERJAKAN, $technicianUserId, $order->started_at],
                [OrderStatus::SELESAI, $technicianUserId, $order->completed_at],
                [OrderStatus::LUNAS, $this->admin->id, $order->paid_at],
            ], 0, (int) array_search($status, self::FLOW, true) + 1),
        };

        $from = null;
        foreach ($steps as [$to, $by, $at]) {
            $log = $order->statusLogs()->make([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $by,
                'note' => $to === $order->status && $order->status_reason ? $order->status_reason : null,
            ]);
            $log->created_at = $at;
            $log->save();

            $from = $to;
        }
    }

    private function seedLeaves(): void
    {
        StaffLeave::create([
            'staff_type' => $this->technicians[3]->getMorphClass(),
            'staff_id' => $this->technicians[3]->id,
            'leave_date' => $this->days['F3']->toDateString(),
            'reason' => 'Acara keluarga',
        ]);

        StaffLeave::create([
            'staff_type' => $this->helpers[1]->getMorphClass(),
            'staff_id' => $this->helpers[1]->id,
            'leave_date' => $this->days['F2']->toDateString(),
            'reason' => 'Sakit',
        ]);
    }

    private function refreshTechnicianRatings(): void
    {
        foreach ($this->technicians as $technician) {
            $technician->update([
                'avg_rating' => round((float) $technician->reviews()->avg('rating'), 2),
                'review_count' => $technician->reviews()->count(),
            ]);
        }
    }

    /**
     * Hari kerja relatif terhadap hari ini: P1..P6 (lampau), D0 (hari ini / hari kerja berikutnya), F1..F4.
     *
     * @return array<string, CarbonImmutable>
     */
    private function workdays(): array
    {
        $working = array_map('intval', $this->settings->working_days);
        $isWorkday = fn (CarbonImmutable $day) => in_array($day->dayOfWeekIso, $working, true);

        $today = CarbonImmutable::today();
        while (! $isWorkday($today)) {
            $today = $today->addDay();
        }

        $days = ['D0' => $today];

        for ($day = $today, $n = 1; $n <= 6; $n++) {
            do {
                $day = $day->subDay();
            } while (! $isWorkday($day));
            $days["P{$n}"] = $day;
        }

        for ($day = $today, $n = 1; $n <= 4; $n++) {
            do {
                $day = $day->addDay();
            } while (! $isWorkday($day));
            $days["F{$n}"] = $day;
        }

        return $days;
    }

    private function createdAt(CarbonImmutable $start): CarbonImmutable
    {
        $planned = $start->subDays(2)->setTime(19, 30);
        $latest = CarbonImmutable::now()->subHours(3);

        return $planned->lessThan($latest) ? $planned : $latest;
    }

    private function nextCode(CarbonImmutable $createdAt): string
    {
        return app(OrderCodeGenerator::class)->next($createdAt);
    }

    private function distanceKm(float $lat, float $lng): float
    {
        return app(DistanceService::class)->fromOffice($lat, $lng, $this->settings);
    }

    private function transportFee(float $distanceKm): int
    {
        return app(TransportFeeCalculator::class)->feeFor($distanceKm, $this->settings);
    }
}
