<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Memverifikasi Firebase ID token (JWT RS256) dengan sertifikat publik Google,
 * tanpa service account. Mengembalikan klaim yang sudah divalidasi.
 */
class FirebaseTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /**
     * @return array{uid: string, email: string, name: ?string, picture: ?string}
     *
     * @throws InvalidArgumentException jika token tidak valid
     */
    public function verify(string $idToken): array
    {
        $projectId = (string) config('services.firebase.project_id');

        if ($projectId === '') {
            throw new InvalidArgumentException('FIREBASE_PROJECT_ID belum diisi.');
        }

        try {
            $claims = (array) JWT::decode($idToken, $this->keys());
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Token Firebase tidak valid: '.$e->getMessage(), previous: $e);
        }

        if (($claims['iss'] ?? null) !== "https://securetoken.google.com/{$projectId}"
            || ($claims['aud'] ?? null) !== $projectId) {
            throw new InvalidArgumentException('Token Firebase bukan untuk project ini.');
        }

        $uid = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? '');
        $provider = (array) ($claims['firebase'] ?? []);

        if ($uid === '' || strlen($uid) > 128) {
            throw new InvalidArgumentException('Token Firebase tidak memiliki subject.');
        }

        if (($provider['sign_in_provider'] ?? null) !== 'google.com') {
            throw new InvalidArgumentException('Token Firebase bukan dari login Google.');
        }

        if ($email === '' || ($claims['email_verified'] ?? false) !== true) {
            throw new InvalidArgumentException('Email Google tidak tersedia atau belum terverifikasi.');
        }

        return [
            'uid' => $uid,
            'email' => $email,
            'name' => isset($claims['name']) ? (string) $claims['name'] : null,
            'picture' => isset($claims['picture']) ? (string) $claims['picture'] : null,
        ];
    }

    /**
     * @return array<string, Key>
     */
    private function keys(): array
    {
        $certs = Cache::remember('firebase.token-certs', now()->addHour(), function () {
            $response = Http::timeout(10)->get(self::CERTS_URL)->throw();

            return $response->json();
        });

        return collect($certs)
            ->map(fn (string $pem) => new Key($pem, 'RS256'))
            ->all();
    }
}
