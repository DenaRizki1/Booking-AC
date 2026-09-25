<?php

use Illuminate\Support\Facades\Route;

/*
| Area teknisi — prefix /teknisi, nama teknisi.*, middleware: web, auth, role:teknisi.
*/

Route::view('/', 'teknisi.tasks')->name('tasks');
