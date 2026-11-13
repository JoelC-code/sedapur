<?php

namespace App\Support;

use App\Exceptions\SupabaseException;
use App\Services\HouseholdService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Anggota rumah aktif untuk tampilan (avatar header dan halaman Rumah & akun).
 *
 * Daftar anggota disimpan di cache 60 detik agar tidak memanggil Data API di setiap halaman.
 * Cache dihapus saat ada yang bergabung atau dikeluarkan.
 */
class HouseholdMembers
{
    private const TTL_SECONDS = 60;

    /** Warna avatar bergiliran; sengaja tanpa warna brand (oranye) agar tidak terbaca sebagai tombol. */
    private const COLORS = ['bg-olive-700', 'bg-info', 'bg-ai', 'bg-warn'];

    /**
     * @return list<array{user_id: string, nama: string, lengkap: string, role: string, joined_at: string, warna: string, is_self: bool}>
     */
    public static function forCurrentHousehold(): array
    {
        $household = SupabaseSession::household();
        $userId = SupabaseSession::user()['id'] ?? null;

        if ($household === null || $userId === null) {
            return [];
        }

        try {
            $members = Cache::remember(self::cacheKey($household['id']), self::TTL_SECONDS, fn () => app(HouseholdService::class)
                ->members((string) SupabaseSession::accessToken(), $household['id']));
        } catch (SupabaseException) {
            return [];
        }

        return array_map(fn (array $member, int $i): array => [
            'user_id' => $member['user_id'],
            'nama' => $member['name'],
            'lengkap' => $member['user_id'] === $userId ? $member['name'].' (kamu)' : $member['name'],
            'role' => $member['role'],
            'joined_at' => Carbon::parse($member['joined_at'])->timezone('Asia/Jakarta')->translatedFormat('j M Y'),
            'warna' => self::COLORS[$i % count(self::COLORS)],
            'is_self' => $member['user_id'] === $userId,
        ], $members, array_keys($members));
    }

    public static function forget(int $householdId): void
    {
        Cache::forget(self::cacheKey($householdId));
    }

    private static function cacheKey(int $householdId): string
    {
        return "household:{$householdId}:members";
    }
}
