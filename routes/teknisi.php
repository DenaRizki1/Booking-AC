<?php

use App\Http\Controllers\Teknisi\AccountController;
use App\Http\Controllers\Teknisi\TaskController;
use Illuminate\Support\Facades\Route;

/*
| Area teknisi — prefix /teknisi, nama teknisi.*, middleware: web, auth, role:teknisi.
*/

Route::get('/', [TaskController::class, 'index'])->name('tasks');
Route::get('/tugas/{order:code}', [TaskController::class, 'show'])->name('tasks.show');
Route::get('/akun', AccountController::class)->name('account');
