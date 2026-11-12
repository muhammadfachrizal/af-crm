<?php

namespace VentureDrake\LaravelCrm\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VentureDrake\LaravelCrm\Models\DeviceToken;

/**
 * Kirim push notification lewat Firebase Cloud Messaging HTTP v1 TANPA paket tambahan.
 *
 * Setup: Firebase Console > Project settings > Service accounts > Generate new private key (file JSON),
 * simpan di storage/app/firebase/service-account.json, lalu .env:
 *   LARAVEL_CRM_FCM_CREDENTIALS=storage/app/firebase/service-account.json
 */
class FcmService
{
    /** Pesan error terakhir (untuk ditampilkan di halaman diagnosa). */
    public static ?string $lastError = null;

    protected static function credentials(): ?array
    {
        $path = config('laravel-crm.fcm.credentials');

        if (! $path) {
            return null;
        }

        $full = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);

        if (! is_file($full)) {
            static::$lastError = 'File service account tidak ditemukan: '.$full;
            Log::warning('FCM: file service account tidak ditemukan: '.$full);

            return null;
        }

        return json_decode(file_get_contents($full), true) ?: null;
    }

    /** Cek konfigurasi: file ada, project_id, dan access token Google bisa diambil. */
    public static function diagnose(): array
    {
        $path = config('laravel-crm.fcm.credentials');
        $cred = static::credentials();

        if (! $path) {
            return ['ok' => false, 'message' => 'LARAVEL_CRM_FCM_CREDENTIALS belum diisi di .env (lalu php artisan config:clear).'];
        }

        if (! $cred) {
            return ['ok' => false, 'message' => static::$lastError ?: 'File service account tidak bisa dibaca.'];
        }

        foreach (['project_id', 'client_email', 'private_key'] as $k) {
            if (empty($cred[$k])) {
                return ['ok' => false, 'message' => "File service account tidak valid (kunci {$k} kosong). Pastikan file JSON dari Firebase > Service accounts."];
            }
        }

        if (! static::accessToken($cred)) {
            Cache::forget('laravel-crm:fcm:token');

            return ['ok' => false, 'message' => static::$lastError ?: 'Gagal mengambil access token.'];
        }

        return ['ok' => true, 'message' => 'Terhubung ke Firebase project '.$cred['project_id'], 'project_id' => $cred['project_id']];
    }

    public static function enabled(): bool
    {
        return static::credentials() !== null;
    }

    protected static function accessToken(array $cred): ?string
    {
        return Cache::remember('laravel-crm:fcm:token', now()->addMinutes(50), function () use ($cred) {
            $now = time();
            $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $cred['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign("$header.$claims", $signature, $cred['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "$header.$claims.".$b64($signature);

            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    /** Kirim ke semua perangkat milik user-user tertentu (di tenant aktif). @return int jumlah terkirim */
    public static function toUsers(array $userIds, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::whereIn('user_id', $userIds)->pluck('token')->all();

        return static::toTokens($tokens, $title, $body, $data);
    }

    public static function toTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $cred = static::credentials();

        if (! $cred || empty($tokens)) {
            return 0;
        }

        $accessToken = static::accessToken($cred);

        if (! $accessToken) {
            Cache::forget('laravel-crm:fcm:token');
            static::$lastError = 'Gagal mendapatkan access token Google (cek isi file service account / waktu server).';
            Log::warning('FCM: gagal mendapatkan access token.');

            return 0;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$cred['project_id']}/messages:send";
        $sent = 0;

        foreach (array_unique($tokens) as $token) {
            $res = Http::withToken($accessToken)->post($url, ['message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                // semua nilai data FCM harus string
                'data' => array_map('strval', $data + ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']),
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'crm_push']],
            ]]);

            if ($res->successful()) {
                $sent++;
            } elseif (in_array($res->json('error.details.0.errorCode') ?? $res->json('error.status'), ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                DeviceToken::withoutGlobalScopes()->where('token', $token)->delete(); // token basi
            } else {
                static::$lastError = 'FCM menolak ('.$res->status().'): '.mb_substr($res->body(), 0, 300);
                Log::warning('FCM: gagal kirim', ['status' => $res->status(), 'body' => $res->body()]);
            }
        }

        return $sent;
    }
}
