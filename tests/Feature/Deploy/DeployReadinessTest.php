<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

it('backup database menulis dump terkompresi dan menghapus backup lama', function () {
    Storage::fake('local');
    Process::fake(['*mysqldump*' => Process::result('-- dump booking_ac')]);

    Storage::disk('local')->put('backups/lama.sql.gz', 'x');
    touch(Storage::disk('local')->path('backups/lama.sql.gz'), now()->subDays(20)->getTimestamp());
    Storage::disk('local')->put('backups/baru.sql.gz', 'x');

    $this->artisan('backup:database')->assertSuccessful();

    $files = Storage::disk('local')->files('backups');
    $created = collect($files)->first(fn (string $file) => str_contains($file, now()->format('Ymd')));

    expect($files)->toHaveCount(2)
        ->and($files)->not->toContain('backups/lama.sql.gz')
        ->and(trim((string) gzdecode(Storage::disk('local')->get($created))))->toBe('-- dump booking_ac');

    // Password tidak dikirim lewat argumen (terlihat di daftar proses), melainkan MYSQL_PWD.
    Process::assertRan(fn ($process) => ! collect($process->command)->contains(fn ($arg) => str_starts_with((string) $arg, '--password')));
});

it('backup gagal bila mysqldump error', function () {
    Storage::fake('local');
    Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

    $this->artisan('backup:database')->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBe([]);
});

it('menjadwalkan backup harian jam 02:00', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'backup:database'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 2 * * *');
});

function proxyCheckRoute(): void
{
    Route::get('/_proxy-check', fn (Request $request) => ['ip' => $request->ip(), 'secure' => $request->isSecure()])->middleware('web');
}

it('membaca IP client & HTTPS dari proxy lokal (Cloudflare Tunnel → Nginx)', function () {
    proxyCheckRoute();

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '36.90.1.2', 'X-Forwarded-Proto' => 'https'])
        ->get('/_proxy-check')
        ->assertJson(['ip' => '36.90.1.2', 'secure' => true]);
});

it('tidak mempercayai header X-Forwarded dari proxy asing', function () {
    proxyCheckRoute();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-For' => '36.90.1.2', 'X-Forwarded-Proto' => 'https'])
        ->get('/_proxy-check')
        ->assertJson(['ip' => '203.0.113.9', 'secure' => false]);
});

it('mengganti password admin lewat artisan', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@booking-ac.test']);

    $this->artisan('user:password', ['email' => 'admin@booking-ac.test'])
        ->expectsQuestion('Password baru (min. 12 karakter)', 'RahasiaBaru2026')
        ->expectsQuestion('Ulangi password baru', 'RahasiaBaru2026')
        ->assertSuccessful();

    expect(Hash::check('RahasiaBaru2026', $admin->fresh()->password))->toBeTrue();
});

it('menolak password lemah atau akun client', function () {
    User::factory()->admin()->create(['email' => 'admin@booking-ac.test']);
    $client = User::factory()->create();

    $this->artisan('user:password', ['email' => 'admin@booking-ac.test'])
        ->expectsQuestion('Password baru (min. 12 karakter)', 'pendek')
        ->expectsQuestion('Ulangi password baru', 'pendek')
        ->assertFailed();

    $this->artisan('user:password', ['email' => $client->email])->assertFailed();
});
