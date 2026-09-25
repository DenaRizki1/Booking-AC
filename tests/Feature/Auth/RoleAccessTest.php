<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function userWithRole(string $role): User
{
    return match ($role) {
        'admin' => User::factory()->admin()->create(),
        'teknisi' => Technician::factory()->create()->user,
        'client' => User::factory()->create(),
    };
}

it('membatasi setiap area ke perannya sendiri', function (string $role, string $path, int $status) {
    $this->actingAs(userWithRole($role))->get($path)->assertStatus($status);
})->with([
    'admin → admin' => ['admin', '/admin', 200],
    'admin → teknisi' => ['admin', '/teknisi', 403],
    'admin → client' => ['admin', '/beranda', 403],
    'teknisi → teknisi' => ['teknisi', '/teknisi', 200],
    'teknisi → admin' => ['teknisi', '/admin', 403],
    'teknisi → client' => ['teknisi', '/beranda', 403],
    'client → client' => ['client', '/beranda', 200],
    'client → admin' => ['client', '/admin', 403],
    'client → teknisi' => ['client', '/teknisi', 403],
]);

it('menampilkan halaman 403 berbahasa Indonesia', function () {
    $this->actingAs(userWithRole('client'))->get('/admin')->assertForbidden()->assertSee('Akses ditolak');
});

it('mengarahkan tamu ke halaman login yang sesuai', function (string $path, string $login) {
    $this->get($path)->assertRedirect($login);
})->with([
    ['/admin', '/login'],
    ['/teknisi', '/login'],
    ['/beranda', '/masuk'],
]);

it('mengeluarkan user yang dinonaktifkan saat sedang login', function (string $role, string $path, string $login) {
    $user = userWithRole($role);
    $user->update(['is_active' => false]);

    $this->actingAs($user)->get($path)
        ->assertRedirect($login)
        ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi admin.');

    $this->assertGuest();
})->with([
    ['client', '/beranda', '/masuk'],
    ['teknisi', '/teknisi', '/login'],
    ['admin', '/admin', '/login'],
]);

it('menerima beberapa peran sekaligus pada middleware role', function () {
    Route::middleware(['web', 'auth', 'role:admin|teknisi'])->get('/_test/staf', fn () => 'ok');

    $this->actingAs(userWithRole('teknisi'))->get('/_test/staf')->assertOk();
    $this->actingAs(userWithRole('admin'))->get('/_test/staf')->assertOk();
    $this->actingAs(userWithRole('client'))->get('/_test/staf')->assertForbidden();
});

describe('OrderPolicy', function () {
    it('hanya mengizinkan client melihat order miliknya', function () {
        $owner = User::factory()->create();
        $order = Order::factory()->for($owner, 'client')->create();

        expect($owner->can('view', $order))->toBeTrue()
            ->and(User::factory()->create()->can('view', $order))->toBeFalse();
    });

    it('hanya mengizinkan teknisi melihat order yang ditugaskan kepadanya', function () {
        $assigned = Technician::factory()->create();
        $order = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $assigned->id]);
        $unassigned = Order::factory()->create();

        expect($assigned->user->can('view', $order))->toBeTrue()
            ->and(Technician::factory()->create()->user->can('view', $order))->toBeFalse()
            ->and($assigned->user->can('view', $unassigned))->toBeFalse();
    });

    it('mengizinkan admin melihat semua order, kecuali akun nonaktif', function () {
        $order = Order::factory()->create();
        $admin = User::factory()->admin()->create();

        expect($admin->can('view', $order))->toBeTrue();

        $admin->update(['is_active' => false]);
        expect($admin->can('view', $order))->toBeFalse();
    });

    it('hanya client yang boleh membuat order', function () {
        expect(userWithRole('client')->can('create', Order::class))->toBeTrue()
            ->and(userWithRole('teknisi')->can('create', Order::class))->toBeFalse();
    });
});
