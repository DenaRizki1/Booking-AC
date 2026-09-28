<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('menampilkan client beserta alamat utama dan jumlah order', function () {
    $client = User::factory()->create(['name' => 'Dena Rizki', 'phone' => '081234567890']);
    CustomerAddress::factory()->for($client)->create(['label' => 'Kantor', 'is_default' => false]);
    CustomerAddress::factory()->for($client)->default()->create(['label' => 'Rumah', 'address' => 'Jl. Kemang Timur No. 5']);
    Order::factory()->count(2)->for($client, 'client')->withStatus(OrderStatus::LUNAS)->create();
    Order::factory()->for($client, 'client')->withStatus(OrderStatus::MENUNGGU)->create();
    $staff = User::factory()->teknisi()->create(['email' => 'teknisi.bukan.client@sejukac.test']);

    $this->get(route('admin.clients.index'))
        ->assertOk()
        ->assertSee('Dena Rizki')
        ->assertSee('081234567890')
        ->assertSee('Rumah')
        ->assertSee('Jl. Kemang Timur No. 5')
        ->assertSee('+'.($client->addresses()->count() - 1).' alamat lain')
        ->assertSee('3 order')
        ->assertSee('1 aktif')
        ->assertDontSee($staff->email);
});

it('mencari client dan memfilter status', function () {
    User::factory()->create(['name' => 'Siti Rahma', 'email' => 'siti@example.com']);
    User::factory()->inactive()->create(['name' => 'Budi Santoso']);

    $this->get(route('admin.clients.index', ['q' => 'siti']))->assertSee('Siti Rahma')->assertDontSee('Budi Santoso');
    $this->get(route('admin.clients.index', ['status' => 'nonaktif']))->assertSee('Budi Santoso')->assertDontSee('Siti Rahma');
    $this->get(route('admin.clients.index', ['q' => 'tidak-ada']))->assertSee('Client tidak ditemukan');
});

it('menonaktifkan client sehingga tidak bisa memakai aplikasi', function () {
    $client = User::factory()->create(['name' => 'Hendra']);
    Order::factory()->for($client, 'client')->withStatus(OrderStatus::DITUGASKAN)->create();

    $this->from(route('admin.clients.index'))
        ->patch(route('admin.clients.toggle', $client))
        ->assertRedirect(route('admin.clients.index'))
        ->assertSessionHas('success', 'Hendra dinonaktifkan: tidak bisa login dan membuat order.')
        ->assertSessionHas('warning');

    expect($client->fresh()->is_active)->toBeFalse();

    $this->actingAs($client->fresh())->get(route('client.home'))->assertRedirect(route('client.login'));
});

it('mengaktifkan kembali client', function () {
    $client = User::factory()->inactive()->create(['name' => 'Hendra']);

    $this->patch(route('admin.clients.toggle', $client))->assertSessionHas('success', 'Hendra diaktifkan dan bisa login kembali.');

    expect($client->fresh()->is_active)->toBeTrue();
});

it('tidak bisa menonaktifkan akun non-client dari halaman ini', function () {
    $this->patch(route('admin.clients.toggle', $this->admin))->assertNotFound();

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('hanya admin yang bisa melihat daftar client', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.clients.index'))->assertForbidden();
});
