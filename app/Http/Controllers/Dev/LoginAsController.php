<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Masuk sebagai user demo tanpa Google/password — HANYA untuk APP_ENV=local,
 * agar tampilan client bisa dicoba sebelum Google OAuth (T-002) diisi.
 */
class LoginAsController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);
        abort_unless($user->is_active, 403);

        Auth::login($user);
        request()->session()->regenerate();

        return redirect($user->homeUrl());
    }
}
