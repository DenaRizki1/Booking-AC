<?php

use App\Models\CompanySettings;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create(['warranty_days' => 7]);
    $this->actingAs(User::factory()->admin()->create());
});

function servicePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Cuci AC',
        'description' => 'Cuci indoor & outdoor.',
        'icon' => 'droplets',
        'requires_helper' => '0',
        'warranty_days' => '',
        'is_active' => '1',
    ], $overrides);
}

it('menampilkan daftar layanan terurut dengan jumlah harga aktif', function () {
    $wash = Service::factory()->create(['name' => 'Cuci AC', 'sort_order' => 1]);
    Service::factory()->create(['name' => 'Instalasi', 'sort_order' => 2, 'requires_helper' => true, 'warranty_days' => 30]);
    ServicePrice::factory()->count(2)->create(['service_id' => $wash->id]);
    ServicePrice::factory()->inactive()->create(['service_id' => $wash->id]);

    $this->get(route('admin.services.index'))
        ->assertOk()
        ->assertSeeInOrder(['Cuci AC', 'Instalasi'])
        ->assertSee('dari 3 kombinasi')
        ->assertSee('Belum ada harga')
        ->assertSee('Wajib helper')
        ->assertSee('30 hari')
        ->assertSee('7 hari');
});

it('menambah layanan dengan slug otomatis dan garansi default', function () {
    Service::factory()->create(['sort_order' => 3]);

    $response = $this->post(route('admin.services.store'), servicePayload());

    $service = Service::query()->where('name', 'Cuci AC')->sole();
    $response->assertRedirect(route('admin.services.edit', $service))->assertSessionHas('success');

    expect($service->slug)->toBe('cuci-ac')
        ->and($service->warranty_days)->toBeNull()
        ->and($service->requires_helper)->toBeFalse()
        ->and($service->is_active)->toBeTrue()
        ->and($service->icon)->toBe('droplets')
        ->and($service->sort_order)->toBe(4);
});

it('membuat slug unik termasuk terhadap layanan yang sudah dihapus', function () {
    Service::factory()->create(['name' => 'Cuci AC', 'slug' => 'cuci-ac'])->delete();

    $this->post(route('admin.services.store'), servicePayload())->assertSessionHasNoErrors();
    $this->post(route('admin.services.store'), servicePayload())->assertSessionHasNoErrors();

    expect(Service::query()->pluck('slug')->sort()->values()->all())->toBe(['cuci-ac-2', 'cuci-ac-3']);
});

it('mengubah layanan dan memperbarui slug hanya bila nama berubah', function () {
    $service = Service::factory()->create(['name' => 'Cuci AC', 'slug' => 'cuci-ac', 'warranty_days' => null]);

    $this->put(route('admin.services.update', $service), servicePayload(['warranty_days' => '14', 'requires_helper' => '1', 'is_active' => '0']))
        ->assertRedirect(route('admin.services.edit', $service))
        ->assertSessionHas('success', 'Layanan disimpan.');

    $service->refresh();
    expect($service->slug)->toBe('cuci-ac')
        ->and($service->warranty_days)->toBe(14)
        ->and($service->requires_helper)->toBeTrue()
        ->and($service->is_active)->toBeFalse();

    $this->put(route('admin.services.update', $service), servicePayload(['name' => 'Cuci AC Premium']));
    expect($service->fresh()->slug)->toBe('cuci-ac-premium');
});

it('memvalidasi input layanan', function (array $overrides, string $field, string $message) {
    $this->from(route('admin.services.create'))
        ->post(route('admin.services.store'), servicePayload($overrides))
        ->assertRedirect(route('admin.services.create'))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'nama kosong' => [['name' => ''], 'name', 'Nama layanan wajib diisi.'],
    'nama panjang' => [['name' => str_repeat('a', 81)], 'name', 'Nama layanan maksimal 80 karakter.'],
    'ikon asing' => [['icon' => 'rocket'], 'icon', 'Ikon yang dipilih tidak valid.'],
    'garansi negatif' => [['warranty_days' => '-1'], 'warranty_days', 'Garansi minimal 0.'],
    'garansi bukan angka' => [['warranty_days' => 'seminggu'], 'warranty_days', 'Garansi harus berupa bilangan bulat.'],
]);

it('menampilkan form tambah dan ubah dengan tab info layanan', function () {
    $this->get(route('admin.services.create'))->assertOk()->assertSee('Tambah layanan')->assertSee('Kosongkan = default pengaturan (7 hari).');

    $service = Service::factory()->create(['name' => 'Perbaikan', 'icon' => 'wrench']);
    $this->get(route('admin.services.edit', $service))
        ->assertOk()
        ->assertSee('Info layanan')
        ->assertSee('Matriks harga')
        ->assertSee('value="wrench" checked', false);
});

it('mengaktifkan/menonaktifkan dan mengurutkan layanan', function () {
    $a = Service::factory()->create(['name' => 'A', 'sort_order' => 1]);
    $b = Service::factory()->create(['name' => 'B', 'sort_order' => 2]);

    $this->patch(route('admin.services.toggle', $a))->assertSessionHas('success', 'A dinonaktifkan dan tidak bisa dipesan client.');
    expect($a->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.services.move', $b), ['direction' => 'up']);
    expect(Service::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['B', 'A']);
});

it('menghapus layanan secara soft delete', function () {
    $service = Service::factory()->create(['name' => 'Isi Freon']);

    $this->delete(route('admin.services.destroy', $service))->assertSessionHas('success');

    expect(Service::query()->find($service->id))->toBeNull()
        ->and(Service::withTrashed()->find($service->id))->not->toBeNull();
    $this->get(route('admin.services.index'))->assertSee('Isi Freon dihapus.')->assertDontSee('Ubah Isi Freon');
});
