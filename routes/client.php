<?php

use App\Http\Controllers\Client\AddressController;
use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Client\Orders\OrderUnitsController;
use App\Http\Controllers\Client\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Area client — middleware: web, auth, role:client, profile.complete (lihat bootstrap/app.php).
*/

Route::get('/lengkapi-profil', [ProfileController::class, 'complete'])->name('profile.complete');
Route::post('/lengkapi-profil', [ProfileController::class, 'storeComplete'])->name('profile.complete.store');

Route::get('/beranda', HomeController::class)->name('home');

Route::get('/profil', [ProfileController::class, 'edit'])->name('profile');
Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');

Route::resource('alamat', AddressController::class)
    ->except('show')
    ->parameters(['alamat' => 'address'])
    ->names('addresses');
Route::patch('/alamat/{address}/utama', [AddressController::class, 'setDefault'])->name('addresses.default');

// Buat order (wizard): 1. unit AC → 2. jadwal & ringkasan → simpan.
Route::get('/order/baru', [OrderUnitsController::class, 'create'])->name('orders.create');
Route::put('/order/baru/draft', [OrderUnitsController::class, 'saveDraft'])->name('orders.draft');
Route::post('/order/baru', [OrderUnitsController::class, 'store'])->name('orders.units');
Route::get('/order/estimate', [OrderUnitsController::class, 'estimate'])->name('orders.estimate');
// Sementara — diganti di T-604 (jadwal, ringkasan & simpan).
Route::get('/order/baru/jadwal', fn () => redirect()->route('client.orders.create'))->name('orders.schedule');
