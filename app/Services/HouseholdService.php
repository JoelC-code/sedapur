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
     * Rumah yang diikuti user (yang paling awal, atau $householdId bila diisi), atau null.
     * Kode undangan hanya terisi untuk owner (kolom invite_code tidak bisa dibaca member).
     *
     * @return array{id: int, name: string, invite_code: string|null, role: string}|null
     */
    public function firstFor(string $accessToken, string $userId, ?int $householdId = null): ?array
    {
        $rows = $this->supabase->select($accessToken, 'household_members', array_filter([
            'select' => 'role,households(id,name)',
            'user_id' => "eq.{$userId}",
            'household_id' => $householdId !== null ? "eq.{$householdId}" : null,
            'order' => 'joined_at.asc',
            'limit' => '1',
        ]));

        if ($rows === []) {
            return null;
        }

        return $this->toHousehold($accessToken, $rows[0]['households'] ?? null, $rows[0]['role'] ?? null);
    }

    /**
     * Buat rumah baru. Trigger households_add_owner menjadikan pembuatnya owner.
     *
     * @return array{id: int, name: string, invite_code: string|null, role: string}
     */
    public function create(string $accessToken, string $name): array
    {
        $row = $this->supabase->insert($accessToken, 'households', ['name' => $name], 'id,name');

        return $this->toHousehold($accessToken, $row, 'owner');
    }

    /**
     * Gabung ke rumah lewat kode undangan (fungsi public.join_household mengembalikan id rumahnya).
     *
     * @return array{id: int, name: string, invite_code: string|null, role: string}
     */
    public function join(string $accessToken, string $userId, string $inviteCode): array
    {
        $householdId = $this->supabase->rpc($accessToken, 'join_household', ['p_invite_code' => $inviteCode]);

        if (! is_numeric($householdId)) {
            throw new SupabaseException('household_not_found', 0, 'join_household tidak mengembalikan id rumah.');
        }

        return $this->firstFor($accessToken, $userId, (int) $householdId)
            ?? throw new SupabaseException('household_not_found', 0, 'Rumah tidak terbaca setelah bergabung.');
    }

    /**
     * Anggota rumah beserta nama dari public.profiles, urut dari yang paling awal bergabung.
     *
     * @return list<array{user_id: string, name: string, role: string, joined_at: string}>
     */
    public function members(string $accessToken, int $householdId): array
    {
        $rows = $this->supabase->select($accessToken, 'household_members', [
            'select' => 'user_id,role,joined_at,profiles(full_name)',
            'household_id' => "eq.{$householdId}",
            'order' => 'joined_at.asc',
        ]);

        $members = [];

        foreach ($rows as $row) {
            if (! is_string($row['user_id'] ?? null) || ! in_array($row['role'] ?? null, ['owner', 'member'], true) || ! is_string($row['joined_at'] ?? null)) {
                throw new SupabaseException('invalid_member', 0, 'Data anggota dari Supabase tidak valid.');
            }

            $members[] = [
                'user_id' => $row['user_id'],
                'name' => is_string($row['profiles']['full_name'] ?? null) ? $row['profiles']['full_name'] : 'Anggota',
                'role' => $row['role'],
                'joined_at' => $row['joined_at'],
            ];
        }

        return $members;
    }

    /**
     * Owner mengeluarkan anggota. RLS household_members hanya mengizinkan owner (atau anggota itu sendiri).
     */
    public function removeMember(string $accessToken, int $householdId, string $userId): void
    {
        $deleted = $this->supabase->delete($accessToken, 'household_members', [
            'household_id' => "eq.{$householdId}",
            'user_id' => "eq.{$userId}",
        ]);

        if ($deleted === 0) {
            throw new SupabaseException('member_not_removed', 0, 'Anggota tidak terhapus (bukan owner atau sudah keluar).');
        }
    }

    /**
     * Validasi bentuk data dari Data API sebelum dipakai.
     *
     * @return array{id: int, name: string, invite_code: string, role: string}
     */
    private function toHousehold(string $accessToken, mixed $row, mixed $role): array
    {
        if (! is_array($row) || ! is_numeric($row['id'] ?? null) || ! is_string($row['name'] ?? null)
            || ! in_array($role, ['owner', 'member'], true)) {
            throw new SupabaseException('invalid_household', 0, 'Data rumah dari Supabase tidak valid.');
        }

        $code = $role === 'owner'
            ? $this->supabase->rpc($accessToken, 'household_invite_code', ['p_household_id' => (int) $row['id']])
            : null;

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'invite_code' => is_string($code) ? $code : null,
            'role' => $role,
        ];
    }

    /**
     * Member keluar dari rumah. RLS household_members mengizinkan user menghapus keanggotaannya sendiri.
     */
    public function leave(string $accessToken, int $householdId, string $userId): void
    {
        $this->removeMember($accessToken, $householdId, $userId);
    }
}
