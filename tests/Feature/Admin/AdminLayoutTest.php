<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Operasional']);
});

it('menampilkan sidebar, nama admin, dan tombol keluar', function () {
    $this->actingAs($this->admin)->get('/admin')
        ->assertOk()
        ->assertSee('Panel Admin')
        ->assertSeeInOrder(['Dashboard', 'Order', 'Jadwal', 'Layanan &amp; Harga', 'Master data', 'Teknisi', 'Helper', 'Client', 'Ulasan', 'Pengaturan'], false)
        ->assertSee('Admin Operasional')
        ->assertSee(route('logout'))
        ->assertSee('aria-current="page"', false);
});

it('menampilkan badge jumlah order MENUNGGU', function () {
    Order::factory()->count(3)->withStatus(OrderStatus::MENUNGGU)->create();
    Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create();

    $this->actingAs($this->admin)->get('/admin')
        ->assertOk()
        ->assertSee('title="Order menunggu">3</span>', false);
});

it('menyembunyikan badge bila tidak ada order MENUNGGU', function () {
    Order::factory()->withStatus(OrderStatus::SELESAI)->create();

    $this->actingAs($this->admin)->get('/admin')
        ->assertOk()
        ->assertDontSee('title="Order menunggu"', false);
});

it('menampilkan pesan flash dari session', function () {
    $this->actingAs($this->admin)
        ->withSession(['success' => 'Data tersimpan.'])
        ->get('/admin')
        ->assertSee('Data tersimpan.');
});

it('merender breadcrumb', function () {
    $html = (string) $this->actingAs($this->admin)->blade(
        '<x-layouts.admin title="Teknisi" :breadcrumbs="[[\'Master data\', null], [\'Teknisi\', \'/admin/teknisi\']]">Isi</x-layouts.admin>'
    );

    expect($html)
        ->toContain('aria-label="Breadcrumb"')
        ->toContain('Master data')
        ->toContain('aria-current="page" class="font-semibold text-ink-soft">Teknisi');
});
