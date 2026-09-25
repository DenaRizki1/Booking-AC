<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Dev\LoginAsController;
use Illuminate\Http\Request;
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

// Alat pengembangan — hanya untuk APP_ENV=local (dicek di tiap handler).
Route::get('/dev/ui', function () {
    abort_unless(app()->environment('local'), 404);

    return view('dev.ui');
})->name('dev.ui');
Route::post('/dev/login-as/{user}', LoginAsController::class)->name('dev.login-as');
