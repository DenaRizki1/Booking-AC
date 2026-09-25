<?php

use App\Http\Controllers\Auth\StaffLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Login admin & teknisi (email + password).
Route::middleware('guest')->group(function () {
    Route::get('/login', [StaffLoginController::class, 'create'])->name('login');
    Route::post('/login', [StaffLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [StaffLoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Halaman awal sementara per peran — diganti di T-303 (route group + middleware role), T-401, T-801.
Route::middleware('auth')->group(function () {
    Route::view('/admin', 'admin.dashboard')->name('admin.dashboard');
    Route::view('/teknisi', 'teknisi.tasks')->name('teknisi.tasks');
});

// Katalog komponen UI — hanya untuk APP_ENV=local.
Route::get('/dev/ui', function () {
    abort_unless(app()->environment('local'), 404);

    return view('dev.ui');
})->name('dev.ui');
