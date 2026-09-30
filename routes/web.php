<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Dev\LoginAsController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Route;

// Halaman awal: tamu → login client, user → halaman awal perannya.
Route::get('/', fn (Request $request) => $request->user()
    ? redirect($request->user()->homeUrl())
    : redirect()->route('client.login'))->name('home');

Route::middleware('guest')->group(function () {
    // Client: login Google.
    Route::get('/masuk', [GoogleLoginController::class, 'create'])->name('client.login');
    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])->name('auth.google.callback');

    // Admin & teknisi: email + password.
    Route::get('/login', [StaffLoginController::class, 'create'])->name('login');
    Route::post('/login', [StaffLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [StaffLoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Notifikasi in-app (ketiga peran).
Route::middleware(['auth', 'role:admin|teknisi|client'])->prefix('notifikasi')->name('notifications.')->controller(NotificationController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/baca-semua', 'readAll')->name('read-all');
    Route::get('/{id}', 'open')->whereUuid('id')->name('open');
});

// Alat pengembangan — hanya untuk APP_ENV=local (dicek di tiap handler).
Route::get('/dev/ui', function () {
    abort_unless(app()->environment('local'), 404);

    return view('dev.ui');
})->name('dev.ui');
Route::get('/dev/login-as/{user}', LoginAsController::class)->name('dev.login-as');
Route::get('/dev/mail', function () {
    abort_unless(app()->environment('local'), 404);

    // Pratinjau template email (tema "booking").
    return (new MailMessage)
        ->subject('Contoh email')
        ->greeting('Halo, Dena!')
        ->line('Order ORD-2609-0012 sudah ditugaskan ke teknisi Rudi Setiawan.')
        ->action('Lihat order', url('/order'))
        ->line('Terima kasih telah menggunakan layanan kami.')
        ->render();
})->name('dev.mail');
