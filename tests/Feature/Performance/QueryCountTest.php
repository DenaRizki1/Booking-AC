<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
| T-1003 · Jumlah query halaman daftar harus tetap (eager loading), tidak bertambah per baris.
*/

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 07:00'));
});

/**
 * Jumlah query untuk satu request GET (setelah satu request pemanasan agar cache pengaturan terisi).
 */
function queriesFor(string $url): int
{
    test()->get($url)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function seedOrders(int $count, array $attributes, OrderStatus $status = OrderStatus::DITUGASKAN): void
{
    Order::factory()->count($count)->at('2026-10-01 10:00', 60)->withStatus($status)->create($attributes)
        ->each(fn (Order $order) => OrderItem::factory()->count(2)->for($order)->create());
}

it('daftar & detail admin tidak N+1', function (string $url) {
    $this->actingAs(User::factory()->admin()->create());
    $technician = Technician::factory()->create();

    seedOrders(2, ['technician_id' => $technician->id]);
    $few = queriesFor($url);

    seedOrders(8, ['technician_id' => $technician->id]);
    Technician::factory()->count(3)->create();

    expect(queriesFor($url))->toBe($few);
})->with([
    'daftar order' => '/admin/order?tab=semua',
    'dashboard' => '/admin',
    'jadwal' => '/admin/jadwal?tanggal=2026-10-01',
    'teknisi' => '/admin/teknisi',
]);

it('daftar ulasan admin tidak N+1', function () {
    $this->actingAs(User::factory()->admin()->create());
    Review::factory()->count(2)->create();
    $few = queriesFor('/admin/ulasan');

    Review::factory()->count(8)->create();

    expect(queriesFor('/admin/ulasan'))->toBe($few);
});

it('daftar tugas teknisi tidak N+1', function () {
    $technician = Technician::factory()->create();
    $this->actingAs($technician->user);

    seedOrders(2, ['technician_id' => $technician->id]);
    $few = queriesFor('/teknisi');

    seedOrders(8, ['technician_id' => $technician->id]);

    expect(queriesFor('/teknisi'))->toBe($few);
});

it('daftar order client tidak N+1', function () {
    $client = User::factory()->create(['phone' => '081234567890']);
    $this->actingAs($client);

    seedOrders(2, ['user_id' => $client->id]);
    $few = queriesFor('/order');

    seedOrders(8, ['user_id' => $client->id]);

    expect(queriesFor('/order'))->toBe($few);
});
