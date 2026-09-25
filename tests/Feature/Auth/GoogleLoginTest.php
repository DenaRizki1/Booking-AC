<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

function fakeGoogleUser(array $attributes = []): GoogleUser
{
    $googleUser = (new GoogleUser)->map(array_merge([
        'id' => '109876543210',
        'name' => 'Dena Rizki',
        'email' => 'dena@gmail.com',
        'avatar' => 'https://lh3.googleusercontent.com/a/foto',
    ], $attributes));

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    return $googleUser;
}

it('menampilkan halaman login client', function () {
    $this->get('/masuk')
        ->assertOk()
        ->assertSee('Masuk dengan Google')
        ->assertSee('Masuk sebagai Admin / Teknisi')
        ->assertDontSee('Mode lokal');
});

it('mengarahkan tamu dari halaman awal ke login client', function () {
    $this->get('/')->assertRedirect('/masuk');
});

it('mengarahkan tombol login ke Google', function () {
    Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $this->get('/auth/google/redirect')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

it('membuat akun client baru dari data Google', function () {
    fakeGoogleUser();

    $this->get('/auth/google/callback')->assertRedirect(route('client.home'));

    $user = User::sole();
    expect($user->role)->toBe(UserRole::CLIENT)
        ->and($user->name)->toBe('Dena Rizki')
        ->and($user->email)->toBe('dena@gmail.com')
        ->and($user->google_id)->toBe('109876543210')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/a/foto')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('menautkan akun client lama lewat email', function () {
    $existing = User::factory()->create(['email' => 'dena@gmail.com', 'google_id' => null]);
    fakeGoogleUser(['email' => 'DENA@gmail.com']);

    $this->get('/auth/google/callback');

    expect(User::count())->toBe(1)
        ->and($existing->fresh()->google_id)->toBe('109876543210');
    $this->assertAuthenticatedAs($existing);
});

it('mengenali user lewat google_id walau email Google berubah', function () {
    $existing = User::factory()->create(['google_id' => '109876543210', 'email' => 'lama@gmail.com']);
    fakeGoogleUser(['email' => 'baru@gmail.com']);

    $this->get('/auth/google/callback');

    expect(User::count())->toBe(1);
    $this->assertAuthenticatedAs($existing);
});

it('menolak akun admin atau teknisi masuk lewat Google', function (string $role) {
    User::factory()->{$role}()->create(['email' => 'staf@gmail.com']);
    fakeGoogleUser(['email' => 'staf@gmail.com']);

    $this->get('/auth/google/callback')
        ->assertRedirect('/masuk')
        ->assertSessionHas('error', 'Akun admin/teknisi masuk lewat halaman login staf, bukan Google.');

    $this->assertGuest();
})->with(['admin', 'teknisi']);

it('menolak client yang dinonaktifkan atau dihapus', function (string $state) {
    $client = User::factory()->create(['email' => 'dena@gmail.com']);
    $state === 'inactive' ? $client->update(['is_active' => false]) : $client->delete();
    fakeGoogleUser();

    $this->get('/auth/google/callback')
        ->assertRedirect('/masuk')
        ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi admin.');

    $this->assertGuest();
    expect(User::withTrashed()->count())->toBe(1);
})->with(['inactive', 'deleted']);

it('menangani login Google yang gagal atau dibatalkan', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get('/auth/google/callback')
        ->assertRedirect('/masuk')
        ->assertSessionHas('error', 'Login Google gagal atau dibatalkan. Silakan coba lagi.');

    $this->assertGuest();
});

it('menyediakan login demo hanya di environment local', function () {
    $client = User::factory()->create();

    $this->post(route('dev.login-as', $client))->assertNotFound();
    $this->assertGuest();

    app()->detectEnvironment(fn () => 'local');

    $this->get('/masuk')->assertSee('Mode lokal')->assertSee($client->name);
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('dev.login-as', $client))
        ->assertRedirect(route('client.home'));
    $this->assertAuthenticatedAs($client);
});
