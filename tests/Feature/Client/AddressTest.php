<?php

use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\User;

beforeEach(function () {
    // Kantor di Kemang; gratis 5 km, maks. 25 km, Rp3.000/km.
    CompanySettings::factory()->create(['lat' => -6.2607, 'lng' => 106.8137]);
    $this->client = User::factory()->create();
});

function addressInput(array $overrides = []): array
{
    return array_merge([
        'label' => 'Rumah',
        'address' => 'Jl. Tebet Raya No. 12, Tebet, Jakarta Selatan',
        'landmark' => 'Seberang minimarket',
        'lat' => -6.2267,   // ± 5,76 km dari kantor
        'lng' => 106.8530,
    ], $overrides);
}

it('menampilkan halaman alamat kosong dan form tambah', function () {
    $this->actingAs($this->client)->get('/alamat')->assertOk()->assertSee('Belum ada alamat');
    $this->actingAs($this->client)->get('/alamat/tambah')->assertOk()
        ->assertSee('Geser peta untuk atur titik')
        ->assertSee('Simpan Alamat');
});

it('menyimpan alamat dalam jangkauan beserta jarak yang dihitung server', function () {
    $this->actingAs($this->client)
        ->post('/alamat', addressInput(['distance_km' => 0.1]))
        ->assertRedirect('/alamat')
        ->assertSessionHas('success', 'Alamat berhasil disimpan.');

    $address = $this->client->addresses()->sole();
    expect($address->label)->toBe('Rumah')
        ->and($address->landmark)->toBe('Seberang minimarket')
        ->and((float) $address->distance_km)->toBe(5.76)
        ->and($address->is_default)->toBeTrue(); // alamat pertama otomatis utama
});

it('menolak alamat di luar radius maksimal', function () {
    $this->actingAs($this->client)
        ->post('/alamat', addressInput(['lat' => -6.5950, 'lng' => 106.8166])) // Bogor, ± 37 km
        ->assertSessionHasErrors(['lat']);

    expect(session('errors')->first('lat'))->toStartWith('Alamat di luar jangkauan layanan')
        ->and($this->client->addresses()->count())->toBe(0);
});

it('memvalidasi isian wajib', function () {
    $this->actingAs($this->client)
        ->post('/alamat', ['label' => '', 'address' => '', 'lat' => '', 'lng' => ''])
        ->assertSessionHasErrors([
            'label' => 'Label alamat wajib diisi.',
            'address' => 'Detail alamat wajib diisi.',
            'lat' => 'Pilih titik lokasi di peta.',
        ]);
});

it('kembali ke halaman asal yang aman setelah menyimpan', function () {
    $this->actingAs($this->client)->post('/alamat', addressInput(['redirect' => '/order/baru']))->assertRedirect('/order/baru');
    $this->actingAs($this->client)->post('/alamat', addressInput(['redirect' => 'https://evil.test']))->assertRedirect('/alamat');
    $this->actingAs($this->client)->post('/alamat', addressInput(['redirect' => '//evil.test']))->assertRedirect('/alamat');
});

it('menjaga hanya satu alamat utama', function () {
    $home = CustomerAddress::factory()->for($this->client)->default()->create();
    $office = CustomerAddress::factory()->for($this->client)->create();

    $this->actingAs($this->client)->patch("/alamat/{$office->id}/utama")->assertRedirect();

    expect($office->fresh()->is_default)->toBeTrue()
        ->and($home->fresh()->is_default)->toBeFalse();

    $this->actingAs($this->client)->post('/alamat', addressInput(['label' => 'Kos', 'is_default' => '1']));

    expect($this->client->addresses()->where('is_default', true)->pluck('label')->all())->toBe(['Kos']);
});

it('memperbarui alamat dan menghitung ulang jarak', function () {
    $address = CustomerAddress::factory()->for($this->client)->create(['distance_km' => 1]);

    $this->actingAs($this->client)->get("/alamat/{$address->id}/ubah")->assertOk()->assertSee('Ubah alamat');
    $this->actingAs($this->client)
        ->put("/alamat/{$address->id}", addressInput(['label' => 'Kantor', 'landmark' => '']))
        ->assertRedirect('/alamat');

    expect($address->fresh())
        ->label->toBe('Kantor')
        ->landmark->toBeNull()
        ->distance_km->toBe('5.76');
});

it('menghapus alamat (soft delete) dan memindahkan alamat utama', function () {
    $home = CustomerAddress::factory()->for($this->client)->default()->create();
    $office = CustomerAddress::factory()->for($this->client)->create();

    $this->actingAs($this->client)->delete("/alamat/{$home->id}")->assertRedirect('/alamat');

    expect($home->fresh()->trashed())->toBeTrue()
        ->and($office->fresh()->is_default)->toBeTrue()
        ->and($this->client->addresses()->count())->toBe(1);
});

it('mencantumkan alamat milik sendiri beserta info transport', function () {
    CustomerAddress::factory()->for($this->client)->default()->create(['label' => 'Rumah', 'distance_km' => 8.4]);
    CustomerAddress::factory()->create(['label' => 'Alamat Orang Lain']);

    $this->actingAs($this->client)->get('/alamat')
        ->assertSee('Rumah')
        ->assertSee('Utama')
        ->assertSee('8,4 km · transport Rp12.000')
        ->assertDontSee('Alamat Orang Lain');
});

it('melarang mengubah atau menghapus alamat client lain', function (string $method, string $suffix) {
    $foreign = CustomerAddress::factory()->create();

    $this->actingAs($this->client)->{$method}("/alamat/{$foreign->id}{$suffix}", addressInput())->assertForbidden();

    expect($foreign->fresh()->trashed())->toBeFalse();
})->with([
    'ubah (form)' => ['get', '/ubah'],
    'simpan' => ['put', ''],
    'utama' => ['patch', '/utama'],
    'hapus' => ['delete', ''],
]);
