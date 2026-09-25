<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('membuat tabel orders sesuai ERD', function () {
    expectTableStructure('orders', [
        'id' => ['bigint unsigned', false],
        'code' => ['varchar(20)', false],
        'order_type' => ['varchar(10)', false],
        'parent_order_id' => ['bigint unsigned', true],
        'user_id' => ['bigint unsigned', false],
        'customer_address_id' => ['bigint unsigned', false],
        'address_snapshot' => ['text', false],
        'lat' => ['decimal(10,7)', false],
        'lng' => ['decimal(10,7)', false],
        'distance_km' => ['decimal(6,2)', false],
        'technician_id' => ['bigint unsigned', true],
        'helper_id' => ['bigint unsigned', true],
        'requires_helper' => ['tinyint(1)', false],
        'scheduled_start' => ['datetime', false],
        'scheduled_end' => ['datetime', false],
        'blocked_until' => ['datetime', false],
        'total_est_minutes' => ['smallint unsigned', false],
        'est_price_min' => ['int unsigned', false],
        'est_price_max' => ['int unsigned', false],
        'transport_fee' => ['int unsigned', false],
        'final_price' => ['int unsigned', true],
        'grand_total' => ['int unsigned', true],
        'customer_note' => ['text', true],
        'technician_note' => ['text', true],
        'status' => ['varchar(20)', false],
        'status_reason' => ['text', true],
        'assigned_at' => ['datetime', true],
        'confirmed_at' => ['datetime', true],
        'departed_at' => ['datetime', true],
        'started_at' => ['datetime', true],
        'completed_at' => ['datetime', true],
        'cancelled_at' => ['datetime', true],
        'payment_method' => ['varchar(10)', true],
        'payment_proof' => ['varchar(255)', true],
        'paid_at' => ['datetime', true],
        'paid_confirmed_by' => ['bigint unsigned', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);
});

it('punya index jadwal, status, dan order client di tabel orders', function () {
    expect(tableIndexes('orders'))->toMatchArray([
        'orders_code_unique' => ['code'],
        'orders_technician_schedule_index' => ['technician_id', 'scheduled_start'],
        'orders_helper_schedule_index' => ['helper_id', 'scheduled_start'],
        'orders_status_schedule_index' => ['status', 'scheduled_start'],
        'orders_user_created_index' => ['user_id', 'created_at'],
        'orders_parent_order_id_index' => ['parent_order_id'],
    ]);
});

it('membuat tabel order_items sesuai ERD', function () {
    expectTableStructure('order_items', [
        'id' => ['bigint unsigned', false],
        'order_id' => ['bigint unsigned', false],
        'claimed_item_id' => ['bigint unsigned', true],
        'service_id' => ['bigint unsigned', false],
        'service_price_id' => ['bigint unsigned', true],
        'ac_brand_id' => ['bigint unsigned', false],
        'ac_capacity_id' => ['bigint unsigned', false],
        'ac_type_id' => ['bigint unsigned', false],
        'service_name' => ['varchar(80)', false],
        'unit_label' => ['varchar(120)', false],
        'est_price_min' => ['int unsigned', false],
        'est_price_max' => ['int unsigned', false],
        'est_minutes' => ['smallint unsigned', false],
        'final_price' => ['int unsigned', true],
        'price_note' => ['text', true],
        'work_note' => ['text', true],
        'warranty_days' => ['smallint unsigned', false],
        'warranty_until' => ['date', true],
        'sort_order' => ['smallint', false],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);

    expect(tableIndexes('order_items'))->toMatchArray([
        'order_items_order_id_foreign' => ['order_id'],
        'order_items_claimed_item_id_foreign' => ['claimed_item_id'],
        'order_items_warranty_until_index' => ['warranty_until'],
    ]);
});

it('membuat tabel order_photos, reviews, order_status_logs sesuai ERD', function () {
    expectTableStructure('order_photos', [
        'id' => ['bigint unsigned', false],
        'order_id' => ['bigint unsigned', false],
        'order_item_id' => ['bigint unsigned', true],
        'kind' => ['varchar(10)', false],
        'path' => ['varchar(255)', false],
        'uploaded_by' => ['bigint unsigned', false],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);

    expectTableStructure('reviews', [
        'id' => ['bigint unsigned', false],
        'order_id' => ['bigint unsigned', false],
        'user_id' => ['bigint unsigned', false],
        'technician_id' => ['bigint unsigned', false],
        'rating' => ['tinyint unsigned', false],
        'comment' => ['text', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);

    expectTableStructure('order_status_logs', [
        'id' => ['bigint unsigned', false],
        'order_id' => ['bigint unsigned', false],
        'from_status' => ['varchar(20)', true],
        'to_status' => ['varchar(20)', false],
        'changed_by' => ['bigint unsigned', true],
        'note' => ['text', true],
        'created_at' => ['timestamp', false],
    ]);

    expect(tableIndexes('order_photos'))->toHaveKey('order_photos_order_item_id_kind_index')
        ->and(tableIndexes('reviews'))->toHaveKeys(['reviews_order_id_unique', 'reviews_technician_id_foreign'])
        ->and(tableIndexes('order_status_logs')['order_status_logs_order_id_created_at_index'])->toBe(['order_id', 'created_at']);
});

it('membuat tabel notifications', function () {
    expect(Schema::hasColumns('notifications', ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at']))->toBeTrue();
});

/**
 * Satu order minimal lengkap dengan 1 unit, dibuat lewat query builder.
 *
 * @return array{order: int, item: int, client: int, technician: int}
 */
function insertOrderFixture(): array
{
    $client = DB::table('users')->insertGetId(['name' => 'Client', 'email' => 'client@example.com']);
    $techUser = DB::table('users')->insertGetId(['name' => 'Teknisi', 'email' => 'tek@example.com', 'role' => 'teknisi']);
    $technician = DB::table('technicians')->insertGetId(['user_id' => $techUser]);
    $address = DB::table('customer_addresses')->insertGetId([
        'user_id' => $client, 'label' => 'Rumah', 'address' => 'Jl. Mawar 1',
        'lat' => -6.2, 'lng' => 106.8, 'distance_km' => 3,
    ]);
    $service = DB::table('services')->insertGetId(['name' => 'Cuci AC', 'slug' => 'cuci-ac']);
    $brand = DB::table('ac_brands')->insertGetId(['name' => 'Daikin']);
    $capacity = DB::table('ac_capacities')->insertGetId(['label' => '1 PK', 'pk_value' => 1]);
    $type = DB::table('ac_types')->insertGetId(['name' => 'Standar']);

    $order = DB::table('orders')->insertGetId([
        'code' => 'ORD-2609-0001', 'user_id' => $client, 'customer_address_id' => $address,
        'address_snapshot' => 'Jl. Mawar 1', 'lat' => -6.2, 'lng' => 106.8, 'distance_km' => 3,
        'technician_id' => $technician,
        'scheduled_start' => '2026-10-01 09:00:00', 'scheduled_end' => '2026-10-01 10:00:00',
        'blocked_until' => '2026-10-01 10:30:00', 'total_est_minutes' => 60,
        'est_price_min' => 75000, 'est_price_max' => 90000,
    ]);

    $item = DB::table('order_items')->insertGetId([
        'order_id' => $order, 'service_id' => $service, 'ac_brand_id' => $brand,
        'ac_capacity_id' => $capacity, 'ac_type_id' => $type, 'service_name' => 'Cuci AC',
        'unit_label' => 'Daikin · 1 PK · Standar', 'est_price_min' => 75000, 'est_price_max' => 90000,
        'est_minutes' => 60, 'warranty_days' => 7,
    ]);

    return compact('order', 'item', 'client', 'technician');
}

it('memberi default order REGULER, status MENUNGGU, transport 0', function () {
    $ids = insertOrderFixture();

    $order = DB::table('orders')->find($ids['order']);

    expect($order->order_type)->toBe('REGULER')
        ->and($order->status)->toBe('MENUNGGU')
        ->and((int) $order->transport_fee)->toBe(0);
});

it('menghapus item, foto, ulasan, dan log saat order dihapus (cascade)', function () {
    $ids = insertOrderFixture();

    DB::table('order_photos')->insert([
        'order_id' => $ids['order'], 'order_item_id' => $ids['item'], 'kind' => 'SESUDAH',
        'path' => 'orders/1/a.jpg', 'uploaded_by' => $ids['client'],
    ]);
    DB::table('reviews')->insert([
        'order_id' => $ids['order'], 'user_id' => $ids['client'], 'technician_id' => $ids['technician'], 'rating' => 5,
    ]);
    DB::table('order_status_logs')->insert(['order_id' => $ids['order'], 'to_status' => 'MENUNGGU']);

    DB::table('orders')->where('id', $ids['order'])->delete();

    expect(DB::table('order_items')->count())->toBe(0)
        ->and(DB::table('order_photos')->count())->toBe(0)
        ->and(DB::table('reviews')->count())->toBe(0)
        ->and(DB::table('order_status_logs')->count())->toBe(0);
});

it('menahan hapus teknisi yang sudah punya order', function () {
    $ids = insertOrderFixture();

    DB::table('technicians')->where('id', $ids['technician'])->delete();
})->throws(QueryException::class);

it('hanya menerima rating 1 sampai 5 (CHECK)', function (int $rating) {
    $ids = insertOrderFixture();

    DB::table('reviews')->insert([
        'order_id' => $ids['order'], 'user_id' => $ids['client'], 'technician_id' => $ids['technician'], 'rating' => $rating,
    ]);
})->with([0, 6])->throws(QueryException::class);

it('hanya boleh satu ulasan per order', function () {
    $ids = insertOrderFixture();
    $review = ['order_id' => $ids['order'], 'user_id' => $ids['client'], 'technician_id' => $ids['technician'], 'rating' => 4];

    DB::table('reviews')->insert($review);
    DB::table('reviews')->insert($review);
})->throws(QueryException::class);

it('mengosongkan changed_by di log saat user pengubah dihapus', function () {
    $ids = insertOrderFixture();
    $admin = DB::table('users')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin']);
    DB::table('order_status_logs')->insert(['order_id' => $ids['order'], 'to_status' => 'DITUGASKAN', 'changed_by' => $admin]);

    DB::table('users')->where('id', $admin)->delete();

    expect(DB::table('order_status_logs')->value('changed_by'))->toBeNull();
});
