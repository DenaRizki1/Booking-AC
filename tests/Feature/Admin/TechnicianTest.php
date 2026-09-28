<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function technicianPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Bima Pratama',
        'email' => 'Bima@SejukAC.test',
        'phone' => '+62 812-3456-7890',
        'password' => 'rahasia123',
        'notes' => 'Spesialis instalasi.',
        'is_active' => '1',
    ], $overrides);
}

it('menampilkan daftar teknisi', function () {
    $technician = Technician::factory()->create(['review_count' => 4, 'avg_rating' => 4.75]);
    Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $technician->id]);

    $this->get(route('admin.technicians.index'))
        ->assertOk()
        ->assertSee($technician->user->name)
        ->assertSee($technician->user->email)
        ->assertSee('4,8')
        ->assertSee('1 aktif');
});

it('menambah teknisi sekaligus akun login', function () {
    Storage::fake('public');

    $response = $this->post(route('admin.technicians.store'), technicianPayload([
        'photo' => UploadedFile::fake()->image('bima.jpg', 300, 300),
    ]));

    $user = User::query()->where('email', 'bima@sejukac.test')->sole();
    $technician = $user->technician;

    $response->assertRedirect(route('admin.technicians.edit', $technician))->assertSessionHas('success');
    expect($user->role)->toBe(UserRole::TEKNISI)
        ->and($user->is_active)->toBeTrue()
        ->and($user->phone)->toBe('081234567890')
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue()
        ->and($technician->notes)->toBe('Spesialis instalasi.')
        ->and($technician->is_active)->toBeTrue();
    Storage::disk('public')->assertExists($technician->photo);
});

it('teknisi baru bisa login di halaman staf', function () {
    $this->post(route('admin.technicians.store'), technicianPayload());
    auth()->logout();

    $this->post(route('login'), ['email' => 'bima@sejukac.test', 'password' => 'rahasia123'])
        ->assertRedirect('/teknisi');
});

it('memvalidasi input teknisi', function (array $overrides, string $field, string $message) {
    User::factory()->create(['email' => 'dipakai@sejukac.test']);

    $this->from(route('admin.technicians.create'))
        ->post(route('admin.technicians.store'), technicianPayload($overrides))
        ->assertRedirect(route('admin.technicians.create'))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'nama kosong' => [['name' => ''], 'name', 'Nama wajib diisi.'],
    'email ganda' => [['email' => 'DIPAKAI@sejukac.test'], 'email', 'Email ini sudah dipakai akun lain.'],
    'email salah' => [['email' => 'bukan-email'], 'email', 'Email harus berupa alamat email yang valid.'],
    'no. HP salah' => [['phone' => '12345'], 'phone', 'Format no. HP tidak valid. Gunakan 08… atau +62…'],
    'password pendek' => [['password' => 'abc'], 'password', 'Password awal minimal 8 karakter.'],
    'password kosong' => [['password' => ''], 'password', 'Password awal wajib diisi.'],
]);

it('mengubah data teknisi tanpa mengubah password', function () {
    $technician = Technician::factory()->create();
    $oldHash = $technician->user->password;

    $this->put(route('admin.technicians.update', $technician), technicianPayload([
        'email' => $technician->user->email,
        'password' => null,
        'name' => 'Nama Baru',
    ]))->assertSessionHasNoErrors()->assertSessionHas('success', 'Data teknisi disimpan.');

    $user = $technician->user->fresh();
    expect($user->name)->toBe('Nama Baru')->and($user->password)->toBe($oldHash);
});

it('mengganti dan menghapus foto teknisi', function () {
    Storage::fake('public');
    $technician = Technician::factory()->create(['photo' => UploadedFile::fake()->image('a.jpg')->store('technicians', 'public')]);
    $old = $technician->photo;

    $this->put(route('admin.technicians.update', $technician), technicianPayload([
        'email' => $technician->user->email, 'password' => null,
        'photo' => UploadedFile::fake()->image('b.png'),
    ]))->assertSessionHasNoErrors();

    $new = $technician->fresh()->photo;
    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old)->assertExists($new);

    $this->put(route('admin.technicians.update', $technician), technicianPayload([
        'email' => $technician->user->email, 'password' => null, 'remove_photo' => '1',
    ]));
    expect($technician->fresh()->photo)->toBeNull();
    Storage::disk('public')->assertMissing($new);
});

it('menonaktifkan teknisi: akun tidak bisa login dan sesi diakhiri', function () {
    config(['session.driver' => 'database']); // sesi teknisi disimpan di tabel sessions (seperti .env)
    $technician = Technician::factory()->create();
    DB::table('sessions')->insert(['id' => 'sesi-teknisi', 'user_id' => $technician->user_id, 'payload' => '', 'last_activity' => time()]);

    $this->from(route('admin.technicians.index'))
        ->patch(route('admin.technicians.toggle', $technician))
        ->assertRedirect(route('admin.technicians.index'))
        ->assertSessionHas('success');

    expect($technician->fresh()->is_active)->toBeFalse()
        ->and($technician->user->fresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sesi-teknisi')->exists())->toBeFalse();

    auth()->logout();
    $this->post(route('login'), ['email' => $technician->user->email, 'password' => 'password'])->assertSessionHasErrors('email');
});

it('memperingatkan bila teknisi yang dinonaktifkan masih punya order aktif', function () {
    $technician = Technician::factory()->create();
    Order::factory()->withStatus(OrderStatus::DIKONFIRMASI)->create(['technician_id' => $technician->id]);

    $this->patch(route('admin.technicians.toggle', $technician))
        ->assertSessionHas('warning', 'Masih ada 1 order aktif yang ditugaskan ke teknisi ini. Assign ulang ke teknisi lain dari halaman order.');
});

it('mengaktifkan kembali teknisi', function () {
    $technician = Technician::factory()->inactive()->create();
    $technician->user->update(['is_active' => false]);

    $this->patch(route('admin.technicians.toggle', $technician))->assertSessionHas('success');

    expect($technician->fresh()->is_active)->toBeTrue()->and($technician->user->fresh()->is_active)->toBeTrue();
});

it('mereset password teknisi', function () {
    config(['session.driver' => 'database']);
    $technician = Technician::factory()->create();
    DB::table('sessions')->insert(['id' => 'sesi-lama', 'user_id' => $technician->user_id, 'payload' => '', 'last_activity' => time()]);

    $this->put(route('admin.technicians.password', $technician), ['password' => 'baru12345'])
        ->assertRedirect(route('admin.technicians.edit', $technician))
        ->assertSessionHas('success');

    expect(Hash::check('baru12345', $technician->user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'sesi-lama')->exists())->toBeFalse();
});

it('memvalidasi password baru di bag terpisah', function () {
    $technician = Technician::factory()->create();

    $this->from(route('admin.technicians.edit', $technician))
        ->put(route('admin.technicians.password', $technician), ['password' => 'pendek'])
        ->assertSessionHasErrorsIn('resetPassword', ['password' => 'Password baru minimal 8 karakter.']);

    $this->get(route('admin.technicians.edit', $technician))->assertSee('Password baru minimal 8 karakter.');
});

it('hanya admin yang bisa mengelola teknisi', function () {
    $technician = Technician::factory()->create();

    $this->actingAs($technician->user)->get(route('admin.technicians.index'))->assertForbidden();
    $this->actingAs($technician->user)->put(route('admin.technicians.password', $technician), ['password' => 'baru12345'])->assertForbidden();
});
