<?php

use App\Jobs\RecalculateAddressDistances;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\DistanceService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->settings = CompanySettings::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Dingin Jaya',
        'phone' => '021-7654321',
        'email' => 'cs@dinginjaya.test',
        'address' => 'Jl. Fatmawati No. 1, Jakarta Selatan',
        'lat' => '-6.2607000',
        'lng' => '106.8137000',
        'open_time' => '07:30',
        'close_time' => '17:00',
        'working_days' => ['6', '1', '2', '3'],
        'slot_interval_minutes' => 30,
        'travel_buffer_minutes' => 45,
        'free_radius_km' => '3.5',
        'max_radius_km' => '20',
        'fee_per_km' => 2500,
        'cancel_limit_hours' => 6,
        'warranty_days' => 14,
        'bank_name' => 'Mandiri',
        'bank_account_no' => '1300012345678',
        'bank_account_name' => 'CV Dingin Jaya',
    ], $overrides);
}

it('menampilkan form pengaturan dengan nilai saat ini', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Titik kantor &amp; jangkauan', false)
        ->assertSee('value="SejukAC"', false)
        ->assertSee('value="08:00"', false)
        ->assertSee('officeMap(', false);
});

it('hanya bisa dibuka admin', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertForbidden();
});

it('menyimpan semua kolom pengaturan dan menyegarkan cache', function () {
    Queue::fake();
    expect(CompanySettings::current()->company_name)->toBe('SejukAC');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsPayload())
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('success', 'Pengaturan disimpan.');

    $settings = CompanySettings::current();
    expect($settings->company_name)->toBe('Dingin Jaya')
        ->and($settings->open_time)->toStartWith('07:30')
        ->and($settings->close_time)->toStartWith('17:00')
        ->and($settings->working_days)->toBe([1, 2, 3, 6])
        ->and($settings->slot_interval_minutes)->toBe(30)
        ->and($settings->free_radius_km)->toBe('3.50')
        ->and($settings->max_radius_km)->toBe('20.00')
        ->and($settings->fee_per_km)->toBe(2500)
        ->and($settings->warranty_days)->toBe(14)
        ->and($settings->bank_account_name)->toBe('CV Dingin Jaya');

    Queue::assertNotPushed(RecalculateAddressDistances::class);
});

it('memvalidasi aturan pengaturan', function (array $overrides, string $field, string $message) {
    $this->actingAs($this->admin)
        ->from(route('admin.settings.edit'))
        ->put(route('admin.settings.update'), settingsPayload($overrides))
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'radius maks ≤ radius gratis' => [['free_radius_km' => '10', 'max_radius_km' => '10'], 'max_radius_km', 'Radius maksimal harus lebih besar dari radius gratis.'],
    'jam tutup ≤ jam buka' => [['open_time' => '17:00', 'close_time' => '08:00'], 'close_time', 'Jam tutup harus setelah jam buka.'],
    'jam tutup sama dengan jam buka' => [['open_time' => '08:00', 'close_time' => '08:00'], 'close_time', 'Jam tutup harus setelah jam buka.'],
    'tanpa hari kerja' => [['working_days' => []], 'working_days', 'Pilih minimal satu hari kerja.'],
    'hari kerja tidak valid' => [['working_days' => ['8']], 'working_days.0', 'Hari kerja tidak valid.'],
    'nama kosong' => [['company_name' => ''], 'company_name', 'Nama perusahaan wajib diisi.'],
    'titik kosong' => [['lat' => ''], 'lat', 'Pilih titik kantor di peta.'],
    'format jam' => [['open_time' => '8 pagi'], 'open_time', 'Format jam buka harus H:i.'],
    'tarif negatif' => [['fee_per_km' => -1], 'fee_per_km', 'Tarif per km minimal 0.'],
]);

it('mengantrekan hitung ulang jarak saat titik kantor dipindah', function () {
    Queue::fake();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), settingsPayload(['lat' => '-6.2000000', 'lng' => '106.8166667']))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'dihitung ulang'));

    Queue::assertPushed(RecalculateAddressDistances::class, 1);
});

it('job menghitung ulang jarak semua alamat dari titik kantor baru', function () {
    $near = CustomerAddress::factory()->create(['lat' => -6.2607, 'lng' => 106.8137, 'distance_km' => 0]);
    $far = CustomerAddress::factory()->create(['lat' => -6.1754, 'lng' => 106.8272, 'distance_km' => 9.6]);

    $this->settings->update(['lat' => -6.1754, 'lng' => 106.8272]);
    (new RecalculateAddressDistances)->handle(app(DistanceService::class));

    $expected = app(DistanceService::class)->between(-6.1754, 106.8272, -6.2607, 106.8137);
    expect((float) $near->fresh()->distance_km)->toBe($expected)
        ->and((float) $far->fresh()->distance_km)->toBe(0.0);
});

it('menyediakan command artisan untuk hitung ulang jarak', function () {
    $address = CustomerAddress::factory()->create(['lat' => -6.2607, 'lng' => 106.8137, 'distance_km' => 12.34]);

    $this->artisan('addresses:recalculate-distances')->assertSuccessful();

    expect((float) $address->fresh()->distance_km)->toBe(0.0);
});
