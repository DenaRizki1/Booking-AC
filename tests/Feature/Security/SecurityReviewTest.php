<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
| T-1002 · Review keamanan: setiap area terlindungi, tidak ada data milik orang lain yang bisa
| dibuka lewat ID/kode, upload dibatasi, rate limit & header keamanan aktif.
*/

beforeEach(function () {
    CompanySettings::factory()->create();
});

/**
 * @return list<RoutingRoute>
 */
function appRoutes(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with((string) $route->getName(), $prefix))
        ->values()
        ->all();
}

it('setiap route admin, teknisi, dan client dilindungi auth + role', function (string $prefix, string $role, array $except) {
    $routes = collect(appRoutes($prefix))->reject(fn (RoutingRoute $route) => in_array($route->getName(), $except, true));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        foreach (['web', 'auth', "role:{$role}"] as $required) {
            expect(in_array($required, $middleware, true))->toBeTrue("{$route->getName()} tanpa middleware {$required}");
        }
    }
})->with([
    'admin' => ['admin.', 'admin', []],
    'teknisi' => ['teknisi.', 'teknisi', []],
    'client' => ['client.', 'client', ['client.login']],
]);

it('semua route yang mengubah data melewati grup web (CSRF aktif)', function () {
    $unsafe = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->reject(fn (RoutingRoute $route) => in_array('web', $route->gatherMiddleware(), true));

    expect($unsafe->map(fn (RoutingRoute $route) => $route->uri())->values()->all())->toBe([]);
});

it('client tidak bisa membuka atau mengubah order & alamat client lain', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 07:00'));
    $owner = User::factory()->create();
    $intruder = User::factory()->create(['phone' => '081200000000']);
    $order = Order::factory()->for($owner, 'client')->at('2026-10-05 10:00')->withStatus(OrderStatus::LUNAS)->create();
    $item = OrderItem::factory()->for($order)->create(['warranty_until' => '2026-10-10']);
    $address = CustomerAddress::factory()->for($owner)->create();

    $this->actingAs($intruder);
    $this->get("/order/{$order->code}")->assertForbidden();
    $this->post("/order/{$order->code}/batal", ['reason' => 'Coba batalkan'])->assertForbidden();
    $this->post("/order/{$order->code}/ulasan", ['rating' => 1])->assertForbidden();
    $this->get("/order/{$order->code}/garansi")->assertForbidden();
    $this->post("/order/{$order->code}/garansi", ['items' => [$item->id], 'complaint' => 'x', 'date' => '2026-10-06', 'time' => '10:00'])->assertForbidden();

    $this->get(route('client.addresses.edit', $address))->assertForbidden();
    $this->put(route('client.addresses.update', $address), ['label' => 'Rumah', 'address' => 'x', 'lat' => -6.26, 'lng' => 106.81])->assertForbidden();
    $this->patch(route('client.addresses.default', $address))->assertForbidden();
    $this->delete(route('client.addresses.destroy', $address))->assertForbidden();

    expect($address->fresh())->not->toBeNull()->and($order->fresh()->review)->toBeNull();
});

it('teknisi tidak bisa membuka atau mengubah tugas teknisi lain', function () {
    Storage::fake('public');
    $mine = Technician::factory()->create();
    $other = Technician::factory()->create();
    $order = Order::factory()->withStatus(OrderStatus::DIKERJAKAN)->create(['technician_id' => $other->id]);
    $item = OrderItem::factory()->for($order)->create();

    $this->actingAs($mine->user);
    $this->get(route('teknisi.tasks.show', $order))->assertForbidden();
    $this->post(route('teknisi.tasks.accept', $order))->assertForbidden();
    $this->get(route('teknisi.reports.edit', $order))->assertForbidden();
    $this->post(route('teknisi.reports.photos.store', [$order, $item]), ['kind' => 'SESUDAH', 'photos' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
    $this->put(route('teknisi.reports.items.update', [$order, $item]), ['final_price' => '1'])->assertForbidden();

    expect($item->fresh()->final_price)->toBeNull()->and($item->photos()->count())->toBe(0);
});

it('foto dari order lain tidak bisa dihapus lewat order milik sendiri', function () {
    $technician = Technician::factory()->create();
    $mine = Order::factory()->withStatus(OrderStatus::DIKERJAKAN)->create(['technician_id' => $technician->id]);
    $foreignPhoto = OrderPhoto::factory()->create();

    $this->actingAs($technician->user)
        ->delete(route('teknisi.reports.photos.destroy', [$mine, $foreignPhoto]))
        ->assertNotFound();

    expect($foreignPhoto->fresh())->not->toBeNull();
});

it('bukti bayar hanya bisa dibuka admin', function () {
    Storage::fake('local');
    Storage::disk('local')->put('payment-proofs/a.jpg', 'x');
    $order = Order::factory()->withStatus(OrderStatus::LUNAS)->create(['payment_proof' => 'payment-proofs/a.jpg']);

    $this->actingAs($order->client)->get(route('admin.orders.payment-proof', $order))->assertForbidden();
    $this->actingAs(Technician::factory()->create()->user)->get(route('admin.orders.payment-proof', $order))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.payment-proof', $order))->assertOk();
});

it('upload hanya menerima gambar maks. 5 MB', function () {
    Storage::fake('public');
    Storage::fake('local');
    $technician = Technician::factory()->create();
    $order = Order::factory()->withStatus(OrderStatus::DIKERJAKAN)->create(['technician_id' => $technician->id]);
    $item = OrderItem::factory()->for($order)->create();

    $this->actingAs($technician->user)
        ->post(route('teknisi.reports.photos.store', [$order, $item]), ['kind' => 'SESUDAH', 'photos' => [UploadedFile::fake()->image('besar.jpg')->size(6000)]])
        ->assertSessionHasErrors(['photos.0' => 'Ukuran foto maksimal 5 MB.']);

    $done = Order::factory()->withStatus(OrderStatus::SELESAI)->create(['grand_total' => 100000]);
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orders.payment', $done), ['payment_method' => 'TRANSFER', 'payment_proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrorsIn('payment', 'payment_proof');

    expect($item->photos()->count())->toBe(0)->and($done->fresh()->status)->toBe(OrderStatus::SELESAI);
});

it('membatasi pembuatan order 10x per menit', function () {
    $client = User::factory()->create(['phone' => '081234567890']);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($client)->post('/order', [])->assertStatus(302);
    }

    $this->actingAs($client)->post('/order', [])->assertStatus(429);
});

it('mengirim header keamanan dasar', function () {
    $this->get('/masuk')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('alat pengembangan tidak tersedia di luar environment lokal', function () {
    $user = User::factory()->create();

    $this->get(route('dev.login-as', $user))->assertNotFound();
    $this->get(route('dev.ui'))->assertNotFound();
    $this->assertGuest();
});
