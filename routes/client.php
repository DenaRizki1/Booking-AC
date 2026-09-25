<?php

use Illuminate\Support\Facades\Route;

/*
| Area client — middleware: web, auth, role:client (lihat bootstrap/app.php).
*/

Route::view('/beranda', 'client.home')->name('home');
