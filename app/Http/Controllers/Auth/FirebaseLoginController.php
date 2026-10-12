<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Login client lewat Google (Firebase Authentication). Browser mengirim Firebase
 * ID token; server memverifikasinya. User baru otomatis dibuat sebagai client;
 * user lama ditautkan lewat email. Admin/teknisi tidak boleh masuk lewat Google.
 */
class FirebaseLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.client-login');
    }

    public function store(Request $request, FirebaseTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string', 'max:4096']]);

        try {
            $google = $verifier->verify($data['id_token']);
        } catch (InvalidArgumentException $e) {
            report($e);

            return $this->fail('Login Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        $email = Str::lower($google['email']);

        $user = User::withTrashed()->where('google_id', $google['uid'])->first()
            ?? User::withTrashed()->where('email', $email)->first();

        if ($user !== null && $user->role !== UserRole::CLIENT) {
            return $this->fail('Akun admin/teknisi masuk lewat halaman login staf, bukan Google.', 403);
        }

        if ($user !== null && ($user->trashed() || ! $user->is_active)) {
            return $this->fail('Akun Anda dinonaktifkan. Silakan hubungi admin.', 403);
        }

        if ($user === null) {
            $user = User::create([
                'name' => $google['name'] ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $google['uid'],
                'avatar' => $google['picture'],
                'role' => UserRole::CLIENT,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        } else {
            $user->forceFill([
                'google_id' => $google['uid'],
                'avatar' => $google['picture'] ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => redirect()->intended($user->homeUrl())->getTargetUrl(),
        ]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
