<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PhotoKind;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create(['company_name' => 'SejukAC', 'bank_name' => 'BCA', 'bank_account_no' => '1234567890', 'bank_account_name' => 'PT Sejuk AC']);
    $this->client = User::factory()->create();
});

function completedOrder(OrderStatus $status = OrderStatus::SELESAI, array $attributes = []): Order
{
    $order = Order::factory()->for(test()->client, 'client')->withStatus($status)->create(array_merge([
        'transport_fee' => 12000, 'final_price' => 255000, 'grand_total' => 267000, 'technician_note' => 'Semua unit normal.',
    ], $attributes));

    $first = OrderItem::factory()->for($order)->create([
        'service_name' => 'Cuci AC', 'unit_label' => 'Daikin · 1 PK · Standar', 'sort_order' => 1,
        'est_price_min' => 75000, 'est_price_max' => 90000, 'final_price' => 85000,
        'work_note' => 'Filter & evaporator sangat kotor, sudah dicuci.',
    ]);
    OrderItem::factory()->for($order)->create([
        'service_name' => 'Cuci AC', 'unit_label' => 'LG · 1½ PK · Inverter', 'sort_order' => 2,
        'est_price_min' => 110000, 'est_price_max' => 120000, 'final_price' => 170000,
        'price_note' => 'ganti kapasitor 35µF',
    ]);
    OrderPhoto::factory()->for($first, 'item')->create(['order_id' => $order->id, 'kind' => PhotoKind::SEBELUM, 'path' => 'orders/a-sebelum.jpg']);
    OrderPhoto::factory()->for($first, 'item')->create(['order_id' => $order->id, 'kind' => PhotoKind::SESUDAH, 'path' => 'orders/a-sesudah.jpg']);

    return $order;
}

it('menampilkan laporan per unit: foto, catatan, harga final & keterangan tambahan', function () {
    $order = completedOrder();

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertOk()
        ->assertSee('Laporan pekerjaan')
        ->assertSeeInOrder(['Unit 1 · Cuci AC', 'Daikin · 1 PK · Standar', 'Rp85.000', 'Filter & evaporator sangat kotor', 'Unit 2 · Cuci AC', 'LG · 1½ PK · Inverter', 'Rp170.000'], false)
        ->assertSee('/storage/orders/a-sebelum.jpg')
        ->assertSee('/storage/orders/a-sesudah.jpg')
        ->assertSee('Belum ada foto')
        ->assertSee('Di atas estimasi: ganti kapasitor 35µF (+Rp50.000)')
        ->assertSee('Catatan teknisi:')
        ->assertDontSee('Total estimasi');
});

it('menampilkan tagihan & info rekening untuk order yang belum dibayar', function () {
    $order = completedOrder();

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertSee('Belum dibayar')
        ->assertSee('Layanan (2 unit)')
        ->assertSee('Rp255.000')
        ->assertSee('Rp12.000')
        ->assertSee('Rp267.000')
        ->assertSeeInOrder(['BCA 1234567890', 'PT Sejuk AC'], false);
});

it('menampilkan status lunas beserta metode pembayaran', function () {
    $order = completedOrder(OrderStatus::LUNAS, ['payment_method' => PaymentMethod::TRANSFER, 'paid_at' => '2026-09-26 15:00:00']);

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertSee('Lunas')
        ->assertSee('Dibayar secara transfer pada Sab, 26 Sep 2026')
        ->assertDontSee('Belum dibayar')
        ->assertDontSee('BCA 1234567890');
});

it('menandai order garansi tanpa tagihan', function () {
    $order = completedOrder(OrderStatus::SELESAI, ['final_price' => 0, 'grand_total' => 0, 'transport_fee' => 0]);

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertSee('Tanpa biaya')
        ->assertSee('Order garansi — tidak ada tagihan.');
});

it('belum menampilkan laporan sebelum order selesai', function () {
    $order = Order::factory()->for($this->client, 'client')->withStatus(OrderStatus::DIKERJAKAN)->create();

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertDontSee('Laporan pekerjaan')
        ->assertDontSee('Pembayaran')
        ->assertSee('Total estimasi');
});
