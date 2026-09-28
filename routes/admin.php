<?php

use App\Http\Controllers\Admin\AcMasterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Area admin — prefix /admin, nama admin.*, middleware: web, auth, role:admin.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/pengaturan', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/pengaturan', [SettingsController::class, 'update'])->name('settings.update');

// Data AC: merk / pk / tipe (satu pola CRUD, lihat AcMasterKind).
Route::prefix('data-ac')->name('ac.')->controller(AcMasterController::class)->group(function () {
    Route::get('/{kind?}', 'index')->name('index');
    Route::post('/{kind}', 'store')->name('store');
    Route::put('/{kind}/{id}', 'update')->whereNumber('id')->name('update');
    Route::patch('/{kind}/{id}/aktif', 'toggle')->whereNumber('id')->name('toggle');
    Route::patch('/{kind}/{id}/urutan', 'move')->whereNumber('id')->name('move');
    Route::delete('/{kind}/{id}', 'destroy')->whereNumber('id')->name('destroy');
});

// Layanan: tab Info layanan + tab Matriks harga.
Route::get('/layanan/{service}/harga', [ServicePriceController::class, 'edit'])->name('services.prices.edit');
Route::put('/layanan/{service}/harga', [ServicePriceController::class, 'update'])->name('services.prices.update');
Route::patch('/layanan/{service}/aktif', [ServiceController::class, 'toggle'])->name('services.toggle');
Route::patch('/layanan/{service}/urutan', [ServiceController::class, 'move'])->name('services.move');
Route::resource('layanan', ServiceController::class)->except('show')->parameters(['layanan' => 'service'])->names('services');
