<?php

use App\Enums\OrderStatus;
use App\Models\CustomerAddress;
use App\Models\Helper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePrice;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AcMasterSeeder;
use Database\Seeders\ServiceSeeder;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 08:00'));
    $this->seed([AcMasterSeeder::class, ServiceSeeder::class]);
    $this->client = User::factory()->create(['name' => 'Dena Rizki']);
});

it('menampilkan sapaan, alamat utama, layanan dengan harga mulai, dan tombol buat order', function () {
    CustomerAddress::factory()->for($this->client)->create(['label' => 'Kantor', 'address' => 'Jl. Sudirman']);
    CustomerAddress::factory()->for($this->client)->default()->create(['label' => 'Rumah', 'address' => 'Jl. Kemang Raya No. 12']);

    $this->actingAs($this->client)->get('/beranda')
        ->assertOk()
        ->assertSee('Selamat pagi,')
        ->assertSee('Dena Rizki')
        ->assertSeeInOrder(['Rumah', 'Jl. Kemang Raya No. 12'])
        ->assertSeeInOrder(['Cuci AC', 'Mulai Rp75rb · ±1 jam', 'Perbaikan', 'Mulai Rp100rb · ±2 jam', 'Instalasi', 'Mulai Rp350rb · ±2 jam', 'Isi Freon', 'Mulai Rp150rb · ±1 jam'])
        ->assertSee('Buat Order')
        ->assertDontSee('Order aktif')
        ->assertDontSee('Garansi aktif');
});

it('mengajak menambah alamat bila belum punya', function () {
    $this->actingAs($this->client)->get('/beranda')->assertSee('Tambah alamat untuk mulai order');
});

it('menyembunyikan layanan yang tidak punya harga aktif', function () {
    ServicePrice::whereHas('service', fn ($q) => $q->where('slug', 'isi-freon'))->update(['is_active' => false]);

    $this->actingAs($this->client)->get('/beranda')->assertDontSee('Isi Freon');
});

it('menampilkan order aktif terdekat beserta teknisi dan progres', function () {
    $technician = Technician::factory()->create(['avg_rating' => 4.9, 'review_count' => 12]);
    $technician->user->update(['name' => 'Rudi Setiawan']);
    $order = Order::factory()->for($this->client, 'client')
        ->at('2026-09-26 11:00', 150)
        ->withStatus(OrderStatus::DALAM_PERJALANAN)
        ->create(['technician_id' => $technician->id, 'helper_id' => Helper::factory()->create(['name' => 'Agus Salim'])->id]);
    OrderItem::factory()->count(2)->for($order)->create();
    Order::factory()->for($this->client, 'client')->at('2026-09-30 09:00')->create(); // MENUNGGU, lebih jauh
    Order::factory()->for($this->client, 'client')->withStatus(OrderStatus::LUNAS)->create();   // bukan order aktif

    $this->actingAs($this->client)->get('/beranda')
        ->assertSee($order->code)
        ->assertSee('Teknisi dalam perjalanan')
        ->assertSee('Rudi Setiawan')
        ->assertSee('★ 4,9')
        ->assertSee('Helper Agus')
        ->assertSee('Sab, 26 Sep · 11:00–13:30')
        ->assertSee('2 unit')
        ->assertSee('+ 1 order aktif lainnya');
});

it('tidak menampilkan order milik client lain', function () {
    $other = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create();

    $this->actingAs($this->client)->get('/beranda')->assertDontSee($other->code);
});

it('menampilkan garansi yang masih berlaku', function () {
    $order = Order::factory()->for($this->client, 'client')->withStatus(OrderStatus::SELESAI)->create();
    OrderItem::factory()->for($order)->create(['warranty_until' => '2026-10-02']);
    $expired = Order::factory()->for($this->client, 'client')->withStatus(OrderStatus::LUNAS)->create();
    OrderItem::factory()->for($expired)->create(['warranty_until' => '2026-09-24']);

    $this->actingAs($this->client)->get('/beranda')
        ->assertSee('Garansi aktif')
        ->assertSee("{$order->code} · s/d 2 Okt 2026")
        ->assertDontSee($expired->code);
});
