<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;

it('menampilkan halaman login admin & teknisi', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Masuk sebagai Admin atau Teknisi')
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false);
});

it('mengarahkan admin ke /admin setelah login', function () {
    $admin = User::factory()->admin()->create();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
    $this->get('/admin')->assertOk()->assertSee($admin->name);
});

it('mengarahkan teknisi ke /teknisi setelah login', function () {
    $teknisi = User::factory()->teknisi()->create();

    $this->post('/login', ['email' => $teknisi->email, 'password' => 'password'])
        ->assertRedirect('/teknisi');

    $this->assertAuthenticatedAs($teknisi);
});

it('mengabaikan huruf besar pada email', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post('/login', ['email' => 'ADMIN@Example.com', 'password' => 'password']);

    $this->assertAuthenticatedAs($admin);
});

it('menolak password yang salah', function () {
    $admin = User::factory()->admin()->create();

    $this->from('/login')
        ->post('/login', ['email' => $admin->email, 'password' => 'salah'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email atau password salah, atau akun tidak aktif.']);

    $this->assertGuest();
});

it('menolak akun yang dinonaktifkan', function () {
    $teknisi = User::factory()->teknisi()->inactive()->create();

    $this->post('/login', ['email' => $teknisi->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('menolak akun yang sudah dihapus (soft delete)', function () {
    $teknisi = User::factory()->teknisi()->create();
    $teknisi->delete();

    $this->post('/login', ['email' => $teknisi->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('tidak mengizinkan client masuk lewat form email + password', function () {
    $client = User::factory()->create(['password' => 'password']);

    $this->post('/login', ['email' => $client->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('memvalidasi email & password wajib diisi', function () {
    $this->post('/login', [])
        ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']);
});

it('membatasi 5 percobaan login per menit', function () {
    $admin = User::factory()->admin()->create();

    foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
        $this->post('/login', ['email' => $admin->email, 'password' => 'salah']);
    }

    // Percobaan ke-6 ditolak walaupun password benar.
    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Terlalu banyak percobaan masuk');
    $this->assertGuest();
});

it('logout mengakhiri sesi', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('mengarahkan staf yang sudah login dari halaman login ke halaman awalnya', function () {
    $this->actingAs(User::factory()->teknisi()->create())
        ->get('/login')
        ->assertRedirect('/teknisi');
});

it('mengarahkan tamu yang membuka /admin ke halaman login', function () {
    $this->get('/admin')->assertRedirect('/login');
});
