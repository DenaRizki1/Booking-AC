<?php

use Illuminate\Support\Facades\DB;

it('membuat tabel users sesuai ERD', function () {
    expectTableStructure('users', [
        'id' => ['bigint unsigned', false],
        'name' => ['varchar(120)', false],
        'email' => ['varchar(191)', false],
        'google_id' => ['varchar(64)', true],
        'avatar' => ['varchar(255)', true],
        'phone' => ['varchar(20)', true],
        'role' => ['varchar(20)', false],
        'password' => ['varchar(255)', true],
        'is_active' => ['tinyint(1)', false],
        'email_verified_at' => ['timestamp', true],
        'remember_token' => ['varchar(100)', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
        'deleted_at' => ['timestamp', true],
    ]);

    expect(tableIndexes('users'))
        ->toMatchArray([
            'users_email_unique' => ['email'],
            'users_google_id_unique' => ['google_id'],
            'users_role_index' => ['role'],
        ]);
});

it('memberi default role client, aktif, dan password boleh kosong', function () {
    DB::table('users')->insert(['name' => 'Budi', 'email' => 'budi@example.com']);

    $user = DB::table('users')->first();

    expect($user->role)->toBe('client')
        ->and((int) $user->is_active)->toBe(1)
        ->and($user->password)->toBeNull();
});

it('membuat tabel company_settings sesuai ERD', function () {
    expectTableStructure('company_settings', [
        'id' => ['bigint unsigned', false],
        'company_name' => ['varchar(120)', false],
        'phone' => ['varchar(20)', true],
        'email' => ['varchar(191)', true],
        'address' => ['text', false],
        'lat' => ['decimal(10,7)', false],
        'lng' => ['decimal(10,7)', false],
        'open_time' => ['time', false],
        'close_time' => ['time', false],
        'working_days' => ['json', false],
        'slot_interval_minutes' => ['smallint unsigned', false],
        'travel_buffer_minutes' => ['smallint unsigned', false],
        'free_radius_km' => ['decimal(6,2)', false],
        'max_radius_km' => ['decimal(6,2)', false],
        'fee_per_km' => ['int unsigned', false],
        'cancel_limit_hours' => ['smallint unsigned', false],
        'warranty_days' => ['smallint unsigned', false],
        'bank_name' => ['varchar(100)', true],
        'bank_account_no' => ['varchar(50)', true],
        'bank_account_name' => ['varchar(120)', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);
});

it('mengisi default pengaturan perusahaan sesuai ERD', function () {
    DB::table('company_settings')->insert([
        'company_name' => 'SejukAC',
        'address' => 'Jl. Contoh No. 1',
        'lat' => -6.2,
        'lng' => 106.8,
    ]);

    $settings = DB::table('company_settings')->first();

    expect($settings->open_time)->toBe('08:00:00')
        ->and($settings->close_time)->toBe('18:00:00')
        ->and(json_decode($settings->working_days))->toBe([1, 2, 3, 4, 5, 6])
        ->and((int) $settings->slot_interval_minutes)->toBe(60)
        ->and((int) $settings->travel_buffer_minutes)->toBe(30)
        ->and($settings->free_radius_km)->toBe('5.00')
        ->and($settings->max_radius_km)->toBe('25.00')
        ->and((int) $settings->fee_per_km)->toBe(3000)
        ->and((int) $settings->cancel_limit_hours)->toBe(3)
        ->and((int) $settings->warranty_days)->toBe(7);
});

it('membuat tabel customer_addresses sesuai ERD', function () {
    expectTableStructure('customer_addresses', [
        'id' => ['bigint unsigned', false],
        'user_id' => ['bigint unsigned', false],
        'label' => ['varchar(50)', false],
        'address' => ['text', false],
        'landmark' => ['varchar(255)', true],
        'lat' => ['decimal(10,7)', false],
        'lng' => ['decimal(10,7)', false],
        'distance_km' => ['decimal(6,2)', false],
        'is_default' => ['tinyint(1)', false],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
        'deleted_at' => ['timestamp', true],
    ]);
});

it('menghapus alamat saat user dihapus (cascade)', function () {
    $userId = DB::table('users')->insertGetId(['name' => 'Budi', 'email' => 'budi@example.com']);
    DB::table('customer_addresses')->insert([
        'user_id' => $userId, 'label' => 'Rumah', 'address' => 'Jl. Mawar 1',
        'lat' => -6.2, 'lng' => 106.8, 'distance_km' => 4.2,
    ]);

    DB::table('users')->where('id', $userId)->delete();

    expect(DB::table('customer_addresses')->count())->toBe(0);
});
