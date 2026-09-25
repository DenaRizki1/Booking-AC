<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // User yang sudah login diarahkan ke halaman awal perannya.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeUrl() ?? '/');

        // Tamu diarahkan ke login staf untuk area admin/teknisi, selain itu ke login client (Google).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*', 'teknisi', 'teknisi/*')
            ? route('login')
            : route('client.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
