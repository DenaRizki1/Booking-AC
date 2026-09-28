<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Area admin — prefix /admin, nama admin.*, middleware: web, auth, role:admin.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/pengaturan', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/pengaturan', [SettingsController::class, 'update'])->name('settings.update');
