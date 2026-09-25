<?php

use App\Models\User;

it('mengarahkan user yang sudah login dari halaman awal ke halaman perannya', function (string $role, string $path) {
    $user = match ($role) {
        'admin' => User::factory()->admin()->create(),
        'teknisi' => User::factory()->teknisi()->create(),
        'client' => User::factory()->create(),
    };

    $this->actingAs($user)->get('/')->assertRedirect($path);
})->with([
    ['admin', '/admin'],
    ['teknisi', '/teknisi'],
    ['client', '/beranda'],
]);
