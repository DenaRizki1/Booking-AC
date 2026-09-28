<?php

use App\Http\Controllers\Admin\AcMasterController;
use App\Http\Controllers\Admin\DashboardController;
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
