<?php

namespace App\Services;

use App\Exceptions\SupabaseException;

/**
 * Rumah (household) milik user, selalu lewat Data API dengan access token user
 * sehingga RLS di tabel households dan household_members ikut menjaga.
 */
class HouseholdService
{
    public function __construct(private SupabaseService $supabase) {}

    /**
     * Rumah pertama yang diikuti user, atau null jika belum punya.
     *
     * @return array{id: int, name: string, invite_code: string, role: string}|null
     */
    public function firstFor(string $accessToken, string $userId): ?array
    {
        $rows = $this->supabase->select($accessToken, 'household_members', [
            'select' => 'role,households(id,name,invite_code)',
            'user_id' => "eq.{$userId}",
            'order' => 'joined_at.asc',
            'limit' => '1',
        ]);

        if ($rows === []) {
            return null;
        }

        return $this->toHousehold($rows[0]['households'] ?? null, $rows[0]['role'] ?? null);
    }

    /**
     * Buat rumah baru. Trigger households_add_owner menjadikan pembuatnya owner.
     *
     * @return array{id: int, name: string, invite_code: string, role: string}
     */
    public function create(string $accessToken, string $name): array
    {
        return $this->toHousehold($this->supabase->insert($accessToken, 'households', ['name' => $name]), 'owner');
    }

    /**
     * Gabung ke rumah lewat kode undangan (fungsi public.join_household).
     *
     * @return array{id: int, name: string, invite_code: string, role: string}
     */
    public function join(string $accessToken, string $userId, string $inviteCode): array
    {
        $this->supabase->rpc($accessToken, 'join_household', ['p_invite_code' => $inviteCode]);

        return $this->firstFor($accessToken, $userId)
            ?? throw new SupabaseException('household_not_found', 0, 'Rumah tidak terbaca setelah bergabung.');
    }

    /**
     * Validasi bentuk data dari Data API sebelum dipakai.
     *
     * @return array{id: int, name: string, invite_code: string, role: string}
     */
    private function toHousehold(mixed $row, mixed $role): array
    {
        if (! is_array($row) || ! is_numeric($row['id'] ?? null) || ! is_string($row['name'] ?? null)
            || ! is_string($row['invite_code'] ?? null) || ! in_array($role, ['owner', 'member'], true)) {
            throw new SupabaseException('invalid_household', 0, 'Data rumah dari Supabase tidak valid.');
        }

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'invite_code' => $row['invite_code'],
            'role' => $role,
        ];
    }
}
