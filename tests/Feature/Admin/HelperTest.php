<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->actingAs(User::factory()->admin()->create());
});

it('menampilkan daftar helper, yang aktif lebih dulu', function () {
    Helper::factory()->inactive()->create(['name' => 'Anton']);
    Helper::factory()->create(['name' => 'Budi', 'notes' => 'Kuat angkat outdoor']);

    $this->get(route('admin.helpers.index'))
        ->assertOk()
        ->assertSeeInOrder(['Budi', 'Anton'])
        ->assertSee('Kuat angkat outdoor');
});

it('menambah helper dengan no. HP opsional', function () {
    $this->post(route('admin.helpers.store'), ['name' => 'Joko', 'phone' => '', 'is_active' => '1'])
        ->assertSessionHasNoErrors();
    $this->post(route('admin.helpers.store'), ['name' => 'Eko', 'phone' => '+62 813 1111 2222', 'notes' => 'Shift pagi', 'is_active' => '1'])
        ->assertSessionHasNoErrors();

    expect(Helper::query()->where('name', 'Joko')->sole()->phone)->toBeNull();
    $eko = Helper::query()->where('name', 'Eko')->sole();
    expect($eko->phone)->toBe('081311112222')->and($eko->notes)->toBe('Shift pagi')->and($eko->is_active)->toBeTrue();
});

it('memvalidasi input helper', function (array $payload, string $field, string $message) {
    $this->from(route('admin.helpers.create'))
        ->post(route('admin.helpers.store'), $payload)
        ->assertRedirect(route('admin.helpers.create'))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'nama kosong' => [['name' => ''], 'name', 'Nama wajib diisi.'],
    'nama panjang' => [['name' => str_repeat('a', 121)], 'name', 'Nama maksimal 120 karakter.'],
    'no. HP salah' => [['name' => 'Joko', 'phone' => '0212'], 'phone', 'Format no. HP tidak valid. Gunakan 08… atau +62…'],
]);

it('mengubah helper', function () {
    $helper = Helper::factory()->create(['name' => 'Lama']);

    $this->put(route('admin.helpers.update', $helper), ['name' => 'Baru', 'phone' => '081234567890', 'is_active' => '0'])
        ->assertRedirect(route('admin.helpers.edit', $helper))
        ->assertSessionHas('success', 'Data helper disimpan.');

    expect($helper->fresh()->only(['name', 'is_active']))->toBe(['name' => 'Baru', 'is_active' => false]);
});

it('menonaktifkan helper dan memperingatkan order aktif', function () {
    $helper = Helper::factory()->create(['name' => 'Joko']);
    Order::factory()->withHelper()->withStatus(OrderStatus::DITUGASKAN)->create(['helper_id' => $helper->id]);

    $this->patch(route('admin.helpers.toggle', $helper))
        ->assertSessionHas('success', 'Joko dinonaktifkan dan tidak muncul saat assign.')
        ->assertSessionHas('warning', 'Masih ada 1 order aktif yang memakai helper ini. Ganti helper dari halaman order.');

    expect($helper->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.helpers.toggle', $helper))->assertSessionHas('success', 'Joko diaktifkan.');
});

it('menampilkan form tambah dan ubah', function () {
    $this->get(route('admin.helpers.create'))->assertOk()->assertSee('Tambah helper');
    $helper = Helper::factory()->create(['name' => 'Rahmat']);
    $this->get(route('admin.helpers.edit', $helper))->assertOk()->assertSee('value="Rahmat"', false);
});

it('hanya admin yang bisa mengelola helper', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.helpers.index'))->assertForbidden();
});
