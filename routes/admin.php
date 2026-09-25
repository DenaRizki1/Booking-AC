<?php

use Illuminate\Support\Facades\Route;

/*
| Area admin — prefix /admin, nama admin.*, middleware: web, auth, role:admin.
*/

Route::view('/', 'admin.dashboard')->name('dashboard');
