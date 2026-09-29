<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PhotoKind;
use App\Enums\UserRole;
use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Helper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Models\OrderStatusLog;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('bisa membuat setiap model lewat factory', function (string $model) {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue();
})->with([
    User::class, CompanySettings::class, CustomerAddress::class, AcBrand::class, AcCapacity::class,
    AcType::class, Service::class, ServicePrice::class, Technician::class, Helper::class, StaffLeave::class,
    Order::class, OrderItem::class, OrderPhoto::class, Review::class, OrderStatusLog::class,
]);

it('menghubungkan order → items → photos', function () {
    $order = Order::factory()->create();
    $second = OrderItem::factory()->for($order)->create(['sort_order' => 2]);
    $first = OrderItem::factory()->for($order)->create(['sort_order' => 1]);
    $photo = OrderPhoto::factory()->for($first, 'item')->create(['order_id' => $order->id, 'kind' => PhotoKind::SEBELUM]);

    expect($order->items->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->photos->first()->is($photo))->toBeTrue()
        ->and($order->photos->first()->is($photo))->toBeTrue()
        ->and($photo->item->is($first))->toBeTrue()
        ->and($photo->order->is($order))->toBeTrue()
        ->and($photo->kind)->toBe(PhotoKind::SEBELUM);
});

it('menghubungkan client dengan alamat & order', function () {
    $client = User::factory()->create();
    $address = CustomerAddress::factory()->for($client)->create();
    $order = Order::factory()->for($client, 'client')->for($address, 'address')->create();

    expect($client->addresses->first()->is($address))->toBeTrue()
        ->and($client->orders->first()->is($order))->toBeTrue()
        ->and($order->client->is($client))->toBeTrue()
        ->and($order->address->is($address))->toBeTrue()
        ->and($address->orders->first()->is($order))->toBeTrue()
        ->and($order->address_snapshot)->toContain($address->address);
});

it('tetap bisa membaca alamat order yang sudah dihapus (soft delete)', function () {
    $order = Order::factory()->create();

    $order->address->delete();

    expect($order->fresh()->address)->not->toBeNull();
});

it('menghubungkan teknisi dengan akun, order, ulasan, dan cuti', function () {
    $technician = Technician::factory()->create();
    $order = Order::factory()->withStatus(OrderStatus::SELESAI)->create(['technician_id' => $technician->id]);
    $review = Review::factory()->create(['order_id' => $order->id]);
    $leave = StaffLeave::factory()->for($technician, 'staff')->create();

    expect($technician->user->role)->toBe(UserRole::TEKNISI)
        ->and($technician->user->technician->is($technician))->toBeTrue()
        ->and($technician->orders->first()->is($order))->toBeTrue()
        ->and($order->technician->is($technician))->toBeTrue()
        ->and($technician->reviews->first()->is($review))->toBeTrue()
        ->and($review->technician->is($technician))->toBeTrue()
        ->and($review->client->is($order->client))->toBeTrue()
        ->and($order->review->is($review))->toBeTrue()
        ->and($technician->leaves->first()->is($leave))->toBeTrue()
        ->and($leave->staff_type)->toBe('technician')
        ->and($leave->staff->is($technician))->toBeTrue();
});

it('menghubungkan helper dengan order dan cuti', function () {
    $order = Order::factory()->withHelper()->create();
    $leave = StaffLeave::factory()->for($order->helper, 'staff')->create();

    expect($order->helper->orders->first()->is($order))->toBeTrue()
        ->and($order->requires_helper)->toBeTrue()
        ->and($leave->staff_type)->toBe('helper')
        ->and($leave->staff->is($order->helper))->toBeTrue();
});

it('menghubungkan layanan dengan matriks harga', function () {
    $price = ServicePrice::factory()->create();

    expect($price->service->prices->first()->is($price))->toBeTrue()
        ->and($price->capacity->servicePrices->first()->is($price))->toBeTrue()
        ->and($price->type->servicePrices->first()->is($price))->toBeTrue();
});

it('men-snapshot harga unit dari matriks harga', function () {
    $price = ServicePrice::factory()->create(['price_min' => 75000, 'price_max' => 90000, 'est_minutes' => 60]);
    $item = OrderItem::factory()->forPrice($price)->create();

    expect($item->servicePrice->is($price))->toBeTrue()
        ->and($item->service->is($price->service))->toBeTrue()
        ->and($item->capacity->is($price->capacity))->toBeTrue()
        ->and($item->type->is($price->type))->toBeTrue()
        ->and($item->brand)->toBeInstanceOf(AcBrand::class)
        ->and($item->service_name)->toBe($price->service->name)
        ->and($item->unit_label)->toBe("{$item->brand->name} · {$price->capacity->label} · {$price->type->name}")
        ->and([$item->est_price_min, $item->est_price_max, $item->est_minutes])->toBe([75000, 90000, 60]);
});

it('menghubungkan order garansi dengan order & unit asal', function () {
    $parent = Order::factory()->withStatus(OrderStatus::LUNAS)->create();
    $parentItem = OrderItem::factory()->for($parent)->create(['warranty_until' => now()->addDays(7)]);

    $claim = Order::factory()->warranty($parent)->create();
    $claimItem = OrderItem::factory()->for($claim)->create(['claimed_item_id' => $parentItem->id]);

    expect($claim->order_type)->toBe(OrderType::GARANSI)
        ->and($claim->parentOrder->is($parent))->toBeTrue()
        ->and($parent->warrantyClaims->first()->is($claim))->toBeTrue()
        ->and($claim->client->is($parent->client))->toBeTrue()
        ->and($claim->est_price_max)->toBe(0)
        ->and($claimItem->claimedItem->is($parentItem))->toBeTrue()
        ->and($parentItem->claims->first()->is($claimItem))->toBeTrue();
});

it('mencatat riwayat status order berurutan', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create();
    OrderStatusLog::factory()->for($order)->create();
    OrderStatusLog::factory()->for($order)->create([
        'from_status' => OrderStatus::MENUNGGU, 'to_status' => OrderStatus::DITUGASKAN, 'changed_by' => $admin->id,
    ]);

    $logs = $order->statusLogs()->with('changedBy')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->from_status)->toBeNull()
        ->and($logs[1]->from_status)->toBe(OrderStatus::MENUNGGU)
        ->and($logs[1]->to_status)->toBe(OrderStatus::DITUGASKAN)
        ->and($logs[1]->changedBy->is($admin))->toBeTrue()
        ->and($logs[1]->updated_at)->toBeNull();
});

it('meng-cast kolom order ke enum, tanggal, dan integer', function () {
    $order = Order::factory()
        ->at('2026-10-05 09:00', 150)
        ->withStatus(OrderStatus::LUNAS)
        ->create(['transport_fee' => 12000, 'est_price_max' => 220000])
        ->fresh();

    expect($order->status)->toBe(OrderStatus::LUNAS)
        ->and($order->order_type)->toBe(OrderType::REGULER)
        ->and($order->payment_method)->toBe(PaymentMethod::TUNAI)
        ->and($order->scheduled_start->format('Y-m-d H:i'))->toBe('2026-10-05 09:00')
        ->and($order->scheduled_end->format('H:i'))->toBe('11:30')
        ->and($order->blocked_until->format('H:i'))->toBe('12:00')
        ->and($order->final_price)->toBe(220000)
        ->and($order->grand_total)->toBe(232000)
        ->and($order->lat)->toBeString()
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->paid_at)->not->toBeNull();
});

it('mengosongkan teknisi untuk order yang masih MENUNGGU', function () {
    $order = Order::factory()->create();

    expect($order->status)->toBe(OrderStatus::MENUNGGU)
        ->and($order->technician_id)->toBeNull();
});

it('menyimpan user baru sebagai client aktif secara default', function () {
    $user = User::create(['name' => 'Budi', 'email' => 'budi@example.com']);

    expect($user->role)->toBe(UserRole::CLIENT)
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasRole(UserRole::CLIENT))->toBeTrue();
});

it('menyediakan admin & teknisi dengan password, client tanpa password', function () {
    expect(User::factory()->admin()->create()->password)->not->toBeNull()
        ->and(User::factory()->teknisi()->create()->google_id)->toBeNull()
        ->and(User::factory()->create()->password)->toBeNull();
});

it('meng-cache CompanySettings::current() dan menghapus cache saat diubah', function () {
    $settings = CompanySettings::factory()->create(['company_name' => 'SejukAC']);

    expect(CompanySettings::current()->company_name)->toBe('SejukAC')
        ->and(Cache::has(CompanySettings::CACHE_KEY))->toBeTrue()
        ->and(CompanySettings::current()->working_days)->toBe([1, 2, 3, 4, 5, 6]);

    $settings->update(['company_name' => 'Dingin Jaya']);

    expect(Cache::has(CompanySettings::CACHE_KEY))->toBeFalse()
        ->and(CompanySettings::current()->company_name)->toBe('Dingin Jaya');
});

it('memberi pesan jelas bila pengaturan perusahaan belum ada', function () {
    CompanySettings::current();
})->throws(RuntimeException::class, 'Pengaturan perusahaan belum diisi');
