<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Login client lewat Google. User baru otomatis dibuat sebagai client;
 * user lama ditautkan lewat email. Admin/teknisi tidak boleh masuk lewat Google.
 */
class GoogleLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.client-login');
    }

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Login Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        $email = Str::lower((string) $googleUser->getEmail());

        if ($email === '') {
            return $this->fail('Akun Google Anda tidak memiliki email.');
        }

        $user = User::withTrashed()->where('google_id', $googleUser->getId())->first()
            ?? User::withTrashed()->where('email', $email)->first();

        if ($user !== null && $user->role !== UserRole::CLIENT) {
            return $this->fail('Akun admin/teknisi masuk lewat halaman login staf, bukan Google.');
        }

        if ($user !== null && ($user->trashed() || ! $user->is_active)) {
            return $this->fail('Akun Anda dinonaktifkan. Silakan hubungi admin.');
        }

        if ($user === null) {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'role' => UserRole::CLIENT,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        } else {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended($user->homeUrl());
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('client.login')->with('error', $message);
    }
}
