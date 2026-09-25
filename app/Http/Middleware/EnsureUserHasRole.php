<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi route ke peran tertentu. Pemakaian: role:admin, role:admin|teknisi, atau role:admin,teknisi.
 * Akun yang dinonaktifkan langsung dikeluarkan.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->is_active) {
            $login = $user->role === UserRole::CLIENT ? 'client.login' : 'login';

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($login)->with('error', 'Akun Anda dinonaktifkan. Silakan hubungi admin.');
        }

        $allowed = array_merge(...array_map(fn (string $role) => explode('|', $role), $roles));

        abort_unless(in_array($user->role->value, $allowed, true), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
