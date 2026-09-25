<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

function priceFor(string $service, string $pk, string $type): ServicePrice
{
    return ServicePrice::query()
        ->whereBelongsTo(Service::where('slug', $service)->firstOrFail())
        ->whereBelongsTo(AcCapacity::where('pk_value', $pk)->firstOrFail(), 'capacity')
        ->whereBelongsTo(AcType::where('name', $type)->firstOrFail(), 'type')
        ->firstOrFail();
}

it('mengisi data awal tanpa data demo di luar environment local', function () {
    $this->seed();

    $admin = User::where('email', AdminSeeder::EMAIL)->firstOrFail();

    expect($admin->role)->toBe(UserRole::ADMIN)
        ->and(Hash::check(AdminSeeder::PASSWORD, $admin->password))->toBeTrue()
        ->and(CompanySettings::count())->toBe(1)
        ->and(AcBrand::pluck('name')->all())->toContain('Daikin', 'Panasonic', 'LG', 'Sharp', 'Gree')
        ->and(AcCapacity::orderBy('sort_order')->pluck('label')->all())->toBe(['½ PK', '¾ PK', '1 PK', '1½ PK', '2 PK', '2½ PK'])
        ->and(AcType::orderBy('sort_order')->pluck('name')->all())->toBe(['Standar', 'Low Watt', 'Inverter'])
        ->and(Service::orderBy('sort_order')->pluck('name')->all())->toBe(['Cuci AC', 'Perbaikan', 'Instalasi', 'Isi Freon'])
        ->and(ServicePrice::count())->toBe(4 * 6 * 3)
        ->and(Order::count())->toBe(0)
        ->and(User::count())->toBe(1);
});

it('aman dijalankan ulang tanpa duplikasi', function () {
    $this->seed();
    $this->seed();

    expect(User::count())->toBe(1)
        ->and(CompanySettings::count())->toBe(1)
        ->and(AcBrand::count())->toBe(8)
        ->and(ServicePrice::count())->toBe(72);
});

it('mengisi matriks Cuci AC sesuai mockup Admin-Harga', function () {
    $this->seed();

    $daikin = priceFor('cuci-ac', '1.00', 'Standar');
    $lg = priceFor('cuci-ac', '1.50', 'Inverter');

    // Contoh 2 unit di mockup Client-Order-Unit: Rp185.000 – Rp220.000, ±2,5 jam.
    expect([$daikin->price_min, $daikin->price_max, $daikin->est_minutes])->toBe([75000, 90000, 60])
        ->and([$lg->price_min, $lg->price_max, $lg->est_minutes])->toBe([110000, 130000, 90])
        ->and($daikin->price_min + $lg->price_min)->toBe(185000)
        ->and($daikin->price_max + $lg->price_max)->toBe(220000)
        ->and(priceFor('cuci-ac', '2.50', 'Low Watt')->is_active)->toBeFalse()
        ->and(ServicePrice::whereBelongsTo(Service::where('slug', 'cuci-ac')->first())->where('is_active', true)->count())->toBe(17);
});

it('menandai Instalasi wajib helper dan harga mulai sesuai beranda', function () {
    $this->seed();

    $startingPrice = fn (string $slug) => ServicePrice::whereBelongsTo(Service::where('slug', $slug)->first())->min('price_min');

    expect(Service::where('slug', 'instalasi')->first()->requires_helper)->toBeTrue()
        ->and(Service::where('requires_helper', true)->count())->toBe(1)
        ->and($startingPrice('cuci-ac'))->toBe(75000)
        ->and($startingPrice('perbaikan'))->toBe(100000)
        ->and($startingPrice('instalasi'))->toBe(350000)
        ->and($startingPrice('isi-freon'))->toBe(150000);
});

it('membuat data demo lengkap untuk semua status', function () {
    $this->seed();
    $this->seed(DemoSeeder::class);

    expect(Technician::count())->toBe(4)
        ->and(Helper::count())->toBe(2)
        ->and(User::where('role', UserRole::CLIENT)->count())->toBe(5)
        ->and(Order::count())->toBe(20)
        ->and(StaffLeave::count())->toBe(2)
        ->and(Order::pluck('status')->unique()->count())->toBe(count(OrderStatus::cases()));

    $claim = Order::where('order_type', OrderType::GARANSI)->sole();
    expect($claim->parentOrder->status)->toBe(OrderStatus::SELESAI)
        ->and($claim->items->first()->claimedItem->order_id)->toBe($claim->parent_order_id)
        ->and($claim->items->first()->claimedItem->warranty_until->isFuture())->toBeTrue();

    Order::with(['items', 'statusLogs'])->each(function (Order $order) {
        expect($order->items)->not->toBeEmpty()
            ->and($order->statusLogs->last()->to_status)->toBe($order->status)
            ->and($order->total_est_minutes)->toBe($order->items->sum('est_minutes'))
            ->and($order->est_price_max)->toBe($order->items->sum('est_price_max'));

        if (in_array($order->status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true) && $order->order_type === OrderType::REGULER) {
            expect($order->grand_total)->toBe($order->items->sum('final_price') + $order->transport_fee);
        }
    });
});

it('menyusun jadwal demo tanpa bentrok dan di dalam jam operasional', function () {
    $this->seed();
    $this->seed(DemoSeeder::class);

    $locking = Order::whereIn('status', OrderStatus::scheduleLockingValues())->get();

    foreach (['technician_id', 'helper_id'] as $column) {
        $locking->whereNotNull($column)->groupBy($column)->each(function ($orders) {
            $orders->values()->each(function (Order $a, int $i) use ($orders) {
                $orders->values()->slice($i + 1)->each(function (Order $b) use ($a) {
                    $overlaps = $a->scheduled_start < $b->blocked_until && $a->blocked_until > $b->scheduled_start;
                    expect($overlaps)->toBeFalse("{$a->code} bentrok dengan {$b->code}");
                });
            });
        });
    }

    Order::all()->each(function (Order $order) {
        expect($order->scheduled_start->format('H:i'))->toBeGreaterThanOrEqual('08:00')
            ->and($order->blocked_until->format('H:i'))->toBeLessThanOrEqual('18:00')
            ->and(in_array($order->scheduled_start->dayOfWeekIso, [1, 2, 3, 4, 5, 6], true))->toBeTrue();
    });
});

it('menghitung ulang rating teknisi dari ulasan demo', function () {
    $this->seed();
    $this->seed(DemoSeeder::class);

    Technician::with('reviews')->each(function (Technician $technician) {
        expect($technician->review_count)->toBe($technician->reviews->count())
            ->and((float) $technician->avg_rating)->toBe(round((float) ($technician->reviews->avg('rating') ?? 0), 2));
    });
});
