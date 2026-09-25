<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client wajib melengkapi no. HP sebelum memakai aplikasi (setelah login Google pertama).
 */
class EnsureClientProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && blank($user->phone) && ! $request->routeIs('client.profile.complete*')) {
            return redirect()->guest(route('client.profile.complete'));
        }

        return $next($request);
    }
}
