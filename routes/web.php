<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Katalog komponen UI — hanya untuk APP_ENV=local.
Route::get('/dev/ui', function () {
    abort_unless(app()->environment('local'), 404);

    return view('dev.ui');
})->name('dev.ui');
