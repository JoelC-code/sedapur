<?php

namespace App\Services;

/**
 * Daftar belanja rumah (public.shopping_list_items), selalu lewat Data API dengan access token user.
 *
 * RLS shopping_list_items memastikan user hanya bisa membaca dan mengubah daftar belanja rumah yang
 * ia ikuti; filter household_id di sini hanya memilih rumah aktif, bukan pengaman.
 */
class ShoppingListService
{
    private const COLUMNS = 'id,name,quantity,unit,is_checked,created_at';

    public function __construct(private SupabaseService $supabase) {}

    /**
     * Item daftar belanja rumah: yang belum dicentang dulu, lalu yang terbaru ditambahkan.
     *
     * @return list<array{id: int, nama: string, jumlah: string, satuan: string, dicentang: bool}>
     */
    public function forHousehold(string $accessToken, int $householdId): array
    {
        $rows = $this->supabase->select($accessToken, 'shopping_list_items', [
            'select' => self::COLUMNS,
            'household_id' => "eq.{$householdId}",
            'order' => 'is_checked.asc,created_at.desc',
        ]);

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'nama' => $row['name'],
            'jumlah' => str_replace('.', ',', (string) (float) $row['quantity']),
            'satuan' => $row['unit'],
            'dicentang' => (bool) $row['is_checked'],
        ], $rows);
    }

    /**
     * Tambah item. added_by diisi database dari auth.uid().
     */
    public function add(string $accessToken, int $householdId, string $name, float $quantity, string $unit): int
    {
        $row = $this->supabase->insert($accessToken, 'shopping_list_items', [
            'household_id' => $householdId,
            'name' => $name,
            'quantity' => $quantity,
            'unit' => $unit,
        ], 'id');

        return (int) ($row['id'] ?? 0);
    }

    /**
     * Centang atau batalkan centang. Pencentang dan waktunya dikosongkan lagi saat dibatalkan.
     *
     * @return bool false bila item tidak ditemukan atau RLS menolak
     */
    public function setChecked(string $accessToken, int $householdId, int $id, bool $checked, string $userId): bool
    {
        return $this->supabase->update($accessToken, 'shopping_list_items', [
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ], [
            'is_checked' => $checked,
            'checked_by' => $checked ? $userId : null,
            'checked_at' => $checked ? now()->toIso8601String() : null,
        ]) > 0;
    }

    /**
     * @return bool false bila item tidak ditemukan atau RLS menolak
     */
    public function remove(string $accessToken, int $householdId, int $id): bool
    {
        return $this->supabase->delete($accessToken, 'shopping_list_items', [
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ]) > 0;
    }
}
