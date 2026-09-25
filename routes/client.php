<?php

use App\Http\Controllers\Client\HomeController;
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
