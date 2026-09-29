<?php

use App\Http\Controllers\Admin\AcMasterController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Admin\OrderAssignmentController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderStatusController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffLeaveController;
use App\Http\Controllers\Admin\TechnicianController;
use Illuminate\Support\Facades\Route;

/*
| Area admin — prefix /admin, nama admin.*, middleware: web, auth, role:admin.
*/

Route::get('/', DashboardController::class)->name('dashboard');

// Operasional order.
Route::get('/order', [OrderController::class, 'index'])->name('orders.index');
Route::get('/order/{order:code}', [OrderController::class, 'show'])->name('orders.show');
Route::put('/order/{order:code}/tim', OrderAssignmentController::class)->name('orders.assign');
Route::post('/order/{order:code}/tolak', [OrderStatusController::class, 'reject'])->name('orders.reject');
Route::post('/order/{order:code}/batal', [OrderStatusController::class, 'cancel'])->name('orders.cancel');

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

// Teknisi (sekaligus akun login).
Route::patch('/teknisi/{technician}/aktif', [TechnicianController::class, 'toggle'])->name('technicians.toggle');
Route::put('/teknisi/{technician}/password', [TechnicianController::class, 'resetPassword'])->name('technicians.password');
Route::resource('teknisi', TechnicianController::class)->except(['show', 'destroy'])->parameters(['teknisi' => 'technician'])->names('technicians');

// Helper (tanpa akun login).
Route::patch('/helper/{helper}/aktif', [HelperController::class, 'toggle'])->name('helpers.toggle');
Route::resource('helper', HelperController::class)->except(['show', 'destroy'])->parameters(['helper' => 'helper'])->names('helpers');

// Cuti teknisi & helper (dipecah per hari).
Route::post('/teknisi/{technician}/cuti', [StaffLeaveController::class, 'storeForTechnician'])->name('technicians.leaves.store');
Route::post('/helper/{helper}/cuti', [StaffLeaveController::class, 'storeForHelper'])->name('helpers.leaves.store');
Route::delete('/cuti/{leave}', [StaffLeaveController::class, 'destroy'])->name('leaves.destroy');

// Client (login Google): lihat & nonaktifkan.
Route::get('/client', [ClientController::class, 'index'])->name('clients.index');
Route::patch('/client/{client}/aktif', [ClientController::class, 'toggle'])->name('clients.toggle');
