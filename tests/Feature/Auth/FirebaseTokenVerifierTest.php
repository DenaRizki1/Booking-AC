<?php

use App\Services\FirebaseTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const FIREBASE_TEST_PROJECT = 'booking-ac-test';

/**
 * Buat pasangan kunci + sertifikat self-signed, dan fake endpoint sertifikat Google.
 *
 * @return array{key: OpenSSLAsymmetricKey, kid: string}
 */
function fakeFirebaseCerts(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'firebase-test'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 1);
    openssl_x509_export($cert, $pem);

    Http::fake(['www.googleapis.com/*' => Http::response(['kid-1' => $pem])]);

    return ['key' => $key, 'kid' => 'kid-1'];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function firebaseToken(array $pair, array $overrides = []): string
{
    $now = time();

    return JWT::encode(array_merge([
        'iss' => 'https://securetoken.google.com/'.FIREBASE_TEST_PROJECT,
        'aud' => FIREBASE_TEST_PROJECT,
        'sub' => 'uid-123',
        'iat' => $now - 10,
        'exp' => $now + 3600,
        'email' => 'dena@gmail.com',
        'email_verified' => true,
        'name' => 'Dena',
        'picture' => 'https://example.test/a.png',
        'firebase' => ['sign_in_provider' => 'google.com'],
    ], $overrides), $pair['key'], 'RS256', $pair['kid']);
}

beforeEach(function () {
    config(['services.firebase.project_id' => FIREBASE_TEST_PROJECT]);
    Cache::forget('firebase.token-certs');
});

it('menerima token Firebase yang valid', function () {
    $pair = fakeFirebaseCerts();

    $claims = app(FirebaseTokenVerifier::class)->verify(firebaseToken($pair));

    expect($claims)->toBe([
        'uid' => 'uid-123',
        'email' => 'dena@gmail.com',
        'name' => 'Dena',
        'picture' => 'https://example.test/a.png',
    ]);
});

it('menolak token yang tidak valid', function (array $overrides) {
    $pair = fakeFirebaseCerts();

    app(FirebaseTokenVerifier::class)->verify(firebaseToken($pair, $overrides));
})->with([
    'kedaluwarsa' => [['exp' => time() - 600, 'iat' => time() - 4000]],
    'project lain (aud)' => [['aud' => 'project-lain']],
    'project lain (iss)' => [['iss' => 'https://securetoken.google.com/project-lain']],
    'bukan provider Google' => [['firebase' => ['sign_in_provider' => 'password']]],
    'email belum terverifikasi' => [['email_verified' => false]],
    'tanpa email' => [['email' => '']],
])->throws(InvalidArgumentException::class);

it('menolak token yang ditandatangani kunci lain', function () {
    fakeFirebaseCerts();
    $other = ['key' => openssl_pkey_new(['private_key_bits' => 2048]), 'kid' => 'kid-1'];

    app(FirebaseTokenVerifier::class)->verify(firebaseToken($other));
})->throws(InvalidArgumentException::class);

it('menolak token sampah', function () {
    fakeFirebaseCerts();

    app(FirebaseTokenVerifier::class)->verify('bukan-jwt');
})->throws(InvalidArgumentException::class);
