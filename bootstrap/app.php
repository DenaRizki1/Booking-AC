<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureClientProfileIsComplete;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'role:client', 'profile.complete'])
                ->name('client.')
                ->group(base_path('routes/client.php'));

            Route::middleware(['web', 'auth', 'role:admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'auth', 'role:teknisi'])
                ->prefix('teknisi')
                ->name('teknisi.')
                ->group(base_path('routes/teknisi.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'profile.complete' => EnsureClientProfileIsComplete::class,
        ]);

        $middleware->web(append: [AddSecurityHeaders::class]);

        // Produksi di balik Cloudflare Tunnel → Nginx (lokal): percayai proxy lokal agar
        // IP client & skema HTTPS terbaca dari header X-Forwarded-*.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

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
