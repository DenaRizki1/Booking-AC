<?php

use App\Http\Controllers\Teknisi\AccountController;
use App\Http\Controllers\Teknisi\ReportController;
use App\Http\Controllers\Teknisi\ReviewController;
use App\Http\Controllers\Teknisi\TaskController;
use App\Http\Controllers\Teknisi\TaskStatusController;
use Illuminate\Support\Facades\Route;

/*
| Area teknisi — prefix /teknisi, nama teknisi.*, middleware: web, auth, role:teknisi.
*/

Route::get('/', [TaskController::class, 'index'])->name('tasks');
Route::get('/tugas/{order:code}', [TaskController::class, 'show'])->name('tasks.show');
Route::get('/akun', AccountController::class)->name('account');

// Aksi status tugas.
Route::post('/tugas/{order:code}/terima', [TaskStatusController::class, 'accept'])->name('tasks.accept');
Route::post('/tugas/{order:code}/tolak', [TaskStatusController::class, 'reject'])->name('tasks.reject');
Route::post('/tugas/{order:code}/berangkat', [TaskStatusController::class, 'depart'])->name('tasks.depart');
Route::post('/tugas/{order:code}/mulai', [TaskStatusController::class, 'start'])->name('tasks.start');

// Laporan pekerjaan per unit.
Route::get('/tugas/{order:code}/laporan', [ReportController::class, 'edit'])->name('reports.edit');
Route::post('/tugas/{order:code}/laporan/unit/{item}/foto', [ReportController::class, 'storePhotos'])->name('reports.photos.store');
Route::delete('/tugas/{order:code}/laporan/foto/{photo}', [ReportController::class, 'destroyPhoto'])->name('reports.photos.destroy');
Route::put('/tugas/{order:code}/laporan/unit/{item}', [ReportController::class, 'updateItem'])->name('reports.items.update');
Route::post('/tugas/{order:code}/laporan/kirim', [ReportController::class, 'submit'])->name('reports.submit');

Route::get('/ulasan', ReviewController::class)->name('reviews');
