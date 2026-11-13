<?php

namespace App\Support;

use App\Exceptions\SupabaseException;

/**
 * Session Supabase milik user yang sedang login, disimpan di session Laravel (server-side).
 *
 * Token tidak pernah dikirim ke browser sebagai cookie terpisah; browser hanya memegang
 * cookie session Laravel yang terenkripsi.
 */
class SupabaseSession
{
    private const KEY = 'supabase';

    private const HOUSEHOLD_KEY = 'household';

    /**
     * Simpan session dari respons Supabase Auth setelah memvalidasi isinya.
     *
     * @param  array<string, mixed>  $session
     */
    public static function store(array $session): void
    {
        $accessToken = $session['access_token'] ?? null;
        $refreshToken = $session['refresh_token'] ?? null;
        $userId = $session['user']['id'] ?? null;
        $expiresAt = $session['expires_at'] ?? (isset($session['expires_in']) ? time() + (int) $session['expires_in'] : null);

        if (! is_string($accessToken) || ! is_string($refreshToken) || ! is_string($userId) || ! is_numeric($expiresAt)) {
            throw new SupabaseException('invalid_session', 0, 'Respons session Supabase tidak lengkap.');
        }

        $email = (string) ($session['user']['email'] ?? '');
        $name = trim((string) ($session['user']['user_metadata']['full_name'] ?? ''));

        session()->put(self::KEY, [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => (int) $expiresAt,
            'user' => [
                'id' => $userId,
                'email' => $email,
                'name' => $name !== '' ? $name : strstr($email, '@', true),
            ],
        ]);
    }

    public static function check(): bool
    {
        return session()->has(self::KEY);
    }

    public static function accessToken(): ?string
    {
        return session(self::KEY.'.access_token');
    }

    public static function refreshToken(): ?string
    {
        return session(self::KEY.'.refresh_token');
    }

    public static function expiresWithin(int $seconds): bool
    {
        return (int) session(self::KEY.'.expires_at', 0) - time() <= $seconds;
    }

    /**
     * @return array{id: string, email: string, name: string}|null
     */
    public static function user(): ?array
    {
        return session(self::KEY.'.user');
    }

    /**
     * Rumah aktif user: id, name, invite_code (hanya untuk owner), role (owner/member).
     *
     * @return array{id: int, name: string, invite_code: string|null, role: string}|null
     */
    public static function household(): ?array
    {
        return session(self::HOUSEHOLD_KEY);
    }

    /**
     * @param  array{id: int, name: string, invite_code: string|null, role: string}  $household
     */
    public static function setHousehold(array $household): void
    {
        session()->put(self::HOUSEHOLD_KEY, $household);
    }

    /**
     * Lupakan rumah aktif (setelah keluar dari rumah); login tetap.
     */
    public static function forgetHousehold(): void
    {
        session()->forget(self::HOUSEHOLD_KEY);
    }

    public static function clear(): void
    {
        session()->forget([self::KEY, self::HOUSEHOLD_KEY]);
    }
}
