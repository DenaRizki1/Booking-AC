<?php

use App\Models\User;
use App\Rules\IndonesianPhone;
use Illuminate\Support\Facades\Validator;

it('mewajibkan client tanpa no. HP melengkapi profil dulu', function () {
    $client = User::factory()->withoutPhone()->create();

    $this->actingAs($client)->get('/beranda')->assertRedirect('/lengkapi-profil');
    $this->actingAs($client)->get('/profil')->assertRedirect('/lengkapi-profil');
    $this->actingAs($client)->get('/lengkapi-profil')->assertOk()->assertSee('Lengkapi profil');
});

it('menyimpan no. HP lalu kembali ke halaman yang dituju', function () {
    $client = User::factory()->withoutPhone()->create();

    $this->actingAs($client)->get('/profil');
    $this->actingAs($client)
        ->post('/lengkapi-profil', ['name' => 'Dena Rizki', 'phone' => '+62 812-3456-7890'])
        ->assertRedirect('/profil');

    expect($client->fresh()->phone)->toBe('081234567890');
    $this->actingAs($client)->get('/beranda')->assertOk();
});

it('melewati halaman lengkapi profil bila no. HP sudah ada', function () {
    $this->actingAs(User::factory()->create())->get('/lengkapi-profil')->assertRedirect('/beranda');
});

it('menampilkan dan memperbarui profil', function () {
    $client = User::factory()->create(['name' => 'Budi', 'phone' => '081111111111']);

    $this->actingAs($client)->get('/profil')
        ->assertOk()
        ->assertSee('Budi')
        ->assertSee($client->email)
        ->assertSee('Keluar');

    $this->actingAs($client)
        ->put('/profil', ['name' => 'Budi Santoso', 'phone' => '6285712345678'])
        ->assertRedirect('/profil')
        ->assertSessionHas('success', 'Profil berhasil disimpan.');

    expect($client->fresh())
        ->name->toBe('Budi Santoso')
        ->phone->toBe('085712345678');
});

it('menolak no. HP dengan format salah', function (string $phone) {
    $this->actingAs(User::factory()->create())
        ->put('/profil', ['name' => 'Budi', 'phone' => $phone])
        ->assertSessionHasErrors(['phone' => 'Format no. HP tidak valid. Gunakan 08… atau +62…']);
})->with([
    'bukan 08' => ['0212345678'],
    'terlalu pendek' => ['08123456'],
    'terlalu panjang' => ['0812345678901234'],
    'huruf' => ['08abc1234567'],
    'kode negara lain' => ['+60123456789'],
]);

it('memvalidasi nama & no. HP wajib', function () {
    $this->actingAs(User::factory()->create())
        ->put('/profil', ['name' => '', 'phone' => ''])
        ->assertSessionHasErrors(['name' => 'Nama wajib diisi.', 'phone' => 'No. HP wajib diisi.']);
});

it('menerima dan menyeragamkan format no. HP Indonesia', function (string $input, string $normalized) {
    expect(Validator::make(['phone' => $input], ['phone' => new IndonesianPhone])->passes())->toBeTrue()
        ->and(IndonesianPhone::normalize($input))->toBe($normalized);
})->with([
    ['081234567890', '081234567890'],
    ['0812 3456 7890', '081234567890'],
    ['+6281234567890', '081234567890'],
    ['62-812-3456-789', '08123456789'],
    ['(0857) 1234.5678', '085712345678'],
]);

it('logout client mengakhiri sesi', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
