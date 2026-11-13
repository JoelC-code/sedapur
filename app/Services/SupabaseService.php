<?php

namespace App\Services;

use App\Exceptions\SupabaseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Pintu ke Supabase: Auth (/auth/v1) dan Data API (/rest/v1).
 *
 * Semua request memakai publishable key. Request ke data milik user juga membawa access token
 * user tersebut, sehingga Postgres tahu siapa yang meminta dan RLS yang menentukan baris mana
 * yang boleh dibaca atau diubah. Secret key tidak dipakai di sini.
 */
class SupabaseService
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Daftar akun baru. Jika konfirmasi email aktif di Supabase, hasilnya tanpa session
     * (access_token tidak ada) dan user harus membuka link di email dulu.
     *
     * @return array<string, mixed>
     */
    public function signUp(string $email, string $password, string $name): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post($this->url('/auth/v1/signup'), [
            'email' => $email,
            'password' => $password,
            'data' => ['full_name' => $name],
        ]));
    }

    /**
     * @return array<string, mixed> session: access_token, refresh_token, expires_at, user
     */
    public function signInWithPassword(string $email, string $password): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post($this->url('/auth/v1/token?grant_type=password'), [
            'email' => $email,
            'password' => $password,
        ]));
    }

    /**
     * Tukar refresh token dengan session baru (access token lama berlaku sekitar 1 jam).
     *
     * @return array<string, mixed>
     */
    public function refreshSession(string $refreshToken): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post($this->url('/auth/v1/token?grant_type=refresh_token'), [
            'refresh_token' => $refreshToken,
        ]));
    }

    /**
     * Cabut session di Supabase supaya refresh token tidak bisa dipakai lagi.
     */
    public function signOut(string $accessToken): void
    {
        $this->send(fn (PendingRequest $http) => $http->withToken($accessToken)->post($this->url('/auth/v1/logout')));
    }

    /**
     * SELECT lewat Data API sebagai user. $query memakai sintaks PostgREST.
     *
     * @param  array<string, string>  $query  contoh: ['select' => 'id,name', 'id' => 'eq.5']
     * @return list<array<string, mixed>>
     */
    public function select(string $accessToken, string $table, array $query): array
    {
        return $this->send(fn (PendingRequest $http) => $http->withToken($accessToken)
            ->get($this->url("/rest/v1/{$table}"), $query));
    }

    /**
     * INSERT lewat Data API sebagai user; mengembalikan baris yang baru dibuat.
     *
     * $columns membatasi kolom yang dikembalikan (perlu bila user tidak boleh membaca semua kolom).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function insert(string $accessToken, string $table, array $values, string $columns = '*'): array
    {
        $rows = $this->send(fn (PendingRequest $http) => $http->withToken($accessToken)
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post($this->url("/rest/v1/{$table}?select=".urlencode($columns)), $values));

        return $rows[0] ?? [];
    }

    /**
     * DELETE lewat Data API sebagai user; mengembalikan jumlah baris yang benar-benar terhapus
     * (0 berarti RLS menolak atau barisnya tidak ada).
     *
     * @param  array<string, string>  $filters  sintaks PostgREST, contoh: ['id' => 'eq.5']
     */
    public function delete(string $accessToken, string $table, array $filters): int
    {
        $rows = $this->send(fn (PendingRequest $http) => $http->withToken($accessToken)
            ->withHeaders(['Prefer' => 'return=representation'])
            ->delete($this->url("/rest/v1/{$table}").'?'.http_build_query($filters)));

        return is_array($rows) ? count($rows) : 0;
    }

    /**
     * Panggil fungsi Postgres di schema public lewat /rest/v1/rpc/<nama>.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function rpc(string $accessToken, string $function, array $arguments): mixed
    {
        return $this->send(fn (PendingRequest $http) => $http->withToken($accessToken)
            ->post($this->url("/rest/v1/rpc/{$function}"), $arguments));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): mixed
    {
        try {
            $response = $request(
                Http::acceptJson()
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->withHeaders(['apikey' => (string) config('services.supabase.publishable_key')])
            );
        } catch (ConnectionException $e) {
            throw new SupabaseException('connection_failed', 0, $e->getMessage());
        }

        if ($response->failed()) {
            throw SupabaseException::fromResponse($response);
        }

        return $response->json();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.supabase.url'), '/').$path;
    }
}
