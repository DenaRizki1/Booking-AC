<?php

use App\Http\Controllers\Client\AddressController;
use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\Orders\OrderScheduleController;
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
Route::get('/order/baru/jadwal', [OrderScheduleController::class, 'create'])->name('orders.schedule');
Route::post('/order', [OrderScheduleController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');

Route::get('/order', [OrderController::class, 'index'])->name('orders.index');
Route::get('/order/{order:code}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/order/{order:code}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');
