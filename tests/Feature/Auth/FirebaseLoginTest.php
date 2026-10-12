<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\FirebaseTokenVerifier;

/**
 * Login lewat Firebase: verifier token di-mock; verifikasi JWT diuji di FirebaseTokenVerifierTest.
 *
 * @param  array<string, mixed>  $claims
 */
function fakeFirebaseUser(array $claims = []): void
{
    $verifier = Mockery::mock(FirebaseTokenVerifier::class);
    $verifier->shouldReceive('verify')->with('token-valid')->andReturn(array_merge([
        'uid' => '109876543210',
        'name' => 'Dena Rizki',
        'email' => 'dena@gmail.com',
        'picture' => 'https://lh3.googleusercontent.com/a/foto',
    ], $claims));

    app()->instance(FirebaseTokenVerifier::class, $verifier);
}

function firebaseLogin(?string $token = 'token-valid'): Illuminate\Testing\TestResponse
{
    return test()->postJson('/auth/firebase', ['id_token' => $token]);
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

it('membuat akun client baru dari data Google', function () {
    fakeFirebaseUser();

    firebaseLogin()->assertOk()->assertJson(['redirect' => route('client.home')]);

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
    fakeFirebaseUser(['email' => 'DENA@gmail.com']);

    firebaseLogin()->assertOk();

    expect(User::count())->toBe(1)
        ->and($existing->fresh()->google_id)->toBe('109876543210');
    $this->assertAuthenticatedAs($existing);
});

it('mengenali user lewat google_id walau email Google berubah', function () {
    $existing = User::factory()->create(['google_id' => '109876543210', 'email' => 'lama@gmail.com']);
    fakeFirebaseUser(['email' => 'baru@gmail.com']);

    firebaseLogin()->assertOk();

    expect(User::count())->toBe(1);
    $this->assertAuthenticatedAs($existing);
});

it('menolak akun admin atau teknisi masuk lewat Google', function (string $role) {
    User::factory()->{$role}()->create(['email' => 'staf@gmail.com']);
    fakeFirebaseUser(['email' => 'staf@gmail.com']);

    firebaseLogin()
        ->assertForbidden()
        ->assertJson(['message' => 'Akun admin/teknisi masuk lewat halaman login staf, bukan Google.']);

    $this->assertGuest();
})->with(['admin', 'teknisi']);

it('menolak client yang dinonaktifkan atau dihapus', function (string $state) {
    $client = User::factory()->create(['email' => 'dena@gmail.com']);
    $state === 'inactive' ? $client->update(['is_active' => false]) : $client->delete();
    fakeFirebaseUser();

    firebaseLogin()
        ->assertForbidden()
        ->assertJson(['message' => 'Akun Anda dinonaktifkan. Silakan hubungi admin.']);

    $this->assertGuest();
    expect(User::withTrashed()->count())->toBe(1);
})->with(['inactive', 'deleted']);

it('menolak token Firebase yang tidak valid', function () {
    $verifier = Mockery::mock(FirebaseTokenVerifier::class);
    $verifier->shouldReceive('verify')->andThrow(new InvalidArgumentException('rusak'));
    app()->instance(FirebaseTokenVerifier::class, $verifier);

    firebaseLogin('token-palsu')
        ->assertUnprocessable()
        ->assertJson(['message' => 'Login Google gagal atau dibatalkan. Silakan coba lagi.']);

    $this->assertGuest();
});

it('mewajibkan id_token', function () {
    firebaseLogin(null)->assertUnprocessable()->assertJsonValidationErrors('id_token');
});

it('menyediakan login demo hanya di environment local', function () {
    $client = User::factory()->create();

    $this->get(route('dev.login-as', $client))->assertNotFound();
    $this->assertGuest();

    app()->detectEnvironment(fn () => 'local');

    $this->get('/masuk')->assertSee('Mode lokal')->assertSee($client->name);
    $this->get(route('dev.login-as', $client))->assertRedirect(route('client.home'));
    $this->assertAuthenticatedAs($client);
});
