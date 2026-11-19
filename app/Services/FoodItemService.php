<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Isi kulkas (public.food_items), selalu lewat Data API dengan access token user.
 *
 * RLS food_items memastikan user hanya bisa membaca dan mengubah bahan di rumah yang ia ikuti;
 * filter household_id di sini hanya memilih rumah aktif, bukan pengaman.
 */
class FoodItemService
{
    private const COLUMNS = 'id,name,category,quantity,initial_quantity,unit,weight_grams,price,purchased_at,expires_at,input_source,status,added_by';

    /** Sama dengan constraint food_items_unit_check di database. */
    public const UNITS = ['g', 'kg', 'ml', 'l', 'butir', 'buah', 'ikat', 'bungkus', 'botol', 'kaleng', 'kotak'];

    /** Satuan takaran: pemakaian sebagian lewat pecahan (¼, ½, ¾). Satuan lain dipakai per 1 buah/butir. */
    public const MEASURED_UNITS = ['g', 'kg', 'ml', 'l'];

    /**
     * Perkiraan umur simpan di kulkas (hari) per kategori, untuk mengisi tanggal kedaluwarsa awal
     * di form. Sengaja konservatif; user tetap bisa mengubahnya sesuai kemasan.
     */
    public const SHELF_DAYS = [
        'sayur' => 5, 'buah' => 7, 'daging' => 2, 'ikan' => 2, 'susu_telur' => 7,
        'bumbu' => 30, 'kemasan' => 90, 'lainnya' => 7,
    ];

    private const SOURCES = ['manual' => 'Input manual', 'scan_struk' => 'Scan struk', 'scan_kulkas' => 'Scan kulkas'];

    public function __construct(private SupabaseService $supabase) {}

    /**
     * Bahan di kulkas rumah, paling cepat kedaluwarsa dulu.
     *
     * @param  array<string, string>  $memberNames  user_id => nama, untuk kolom "ditambah oleh"
     * @return list<array<string, mixed>>
     */
    public function list(string $accessToken, int $householdId, string $status, ?string $category, ?string $search, array $memberNames): array
    {
        // Karakter yang punya arti di sintaks filter PostgREST dibuang dari kata pencarian.
        $search = trim(str_replace(['*', '%', ',', '(', ')', '"'], '', (string) $search));

        $rows = $this->supabase->select($accessToken, 'food_items', array_filter([
            'select' => self::COLUMNS,
            'household_id' => "eq.{$householdId}",
            'status' => "eq.{$status}",
            'category' => $category !== null ? "eq.{$category}" : null,
            'name' => $search !== '' ? "ilike.*{$search}*" : null,
            'order' => 'expires_at.asc.nullslast,name.asc',
        ]));

        return array_map(fn (array $row): array => $this->toViewItem($row, $memberNames), $rows);
    }

    /**
     * @param  array<string, string>  $memberNames
     * @return array<string, mixed>|null null bila tidak ada atau bukan milik rumah user (RLS)
     */
    public function find(string $accessToken, int $householdId, int $id, array $memberNames): ?array
    {
        $rows = $this->supabase->select($accessToken, 'food_items', [
            'select' => self::COLUMNS,
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ]);

        return isset($rows[0]) ? $this->toViewItem($rows[0], $memberNames) : null;
    }

    /**
     * Tambah bahan. added_by diisi database dari auth.uid(), initial_quantity oleh trigger.
     *
     * @param  array{name: string, category: string, quantity: float|int|string, unit: string, weight_grams?: mixed, price?: mixed, purchased_at?: mixed, expires_at: string}  $values
     */
    public function create(string $accessToken, int $householdId, array $values): int
    {
        $row = $this->supabase->insert($accessToken, 'food_items', [
            ...$values,
            'household_id' => $householdId,
            'input_source' => 'manual',
        ], 'id');

        return (int) ($row['id'] ?? 0);
    }

    /**
     * Ubah data bahan. Jumlah awal (initial_quantity) tidak ikut berubah: tidak ada grant update-nya.
     *
     * @param  array<string, mixed>  $values  hasil validasi StoreFoodItemRequest
     * @return bool false bila bahan tidak ditemukan atau RLS menolak
     */
    public function update(string $accessToken, int $householdId, int $id, array $values): bool
    {
        return $this->supabase->update($accessToken, 'food_items', [
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ], $values) > 0;
    }

    /**
     * Pakai sebagian bahan lewat RPC use_food_item (atomik di database).
     *
     * @return array{id: int, quantity: float|int, status: string}|null null bila tidak ada baris yang berubah
     */
    public function use(string $accessToken, int $id, float $amount): ?array
    {
        $rows = $this->supabase->rpc($accessToken, 'use_food_item', ['p_item_id' => $id, 'p_amount' => $amount]);

        return is_array($rows[0] ?? null) ? $rows[0] : null;
    }

    /**
     * Tandai bahan dimasak/dibuang. Trigger mengisi status_changed_at dan status_changed_by.
     *
     * @return bool false bila bahan tidak ditemukan atau RLS menolak
     */
    public function setStatus(string $accessToken, int $householdId, int $id, string $status): bool
    {
        return $this->supabase->update($accessToken, 'food_items', [
            'id' => "eq.{$id}",
            'household_id' => "eq.{$householdId}",
        ], ['status' => $status]) > 0;
    }

    /**
     * Bentuk data yang sudah dipakai view kulkas (sama dengan DemoData::foodItems()).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $memberNames
     * @return array<string, mixed>
     */
    private function toViewItem(array $row, array $memberNames): array
    {
        $expiresAt = $row['expires_at'] ? Carbon::parse($row['expires_at'], 'Asia/Jakarta') : null;
        $quantity = (float) $row['quantity'];
        $initial = max((float) ($row['initial_quantity'] ?? $quantity), $quantity);

        return [
            'id' => (int) $row['id'],
            'nama' => $row['name'],
            'kategori' => $row['category'],
            'jumlah' => str_replace('.', ',', (string) $quantity),
            'jumlah_angka' => $quantity,
            'sisa_persen' => (int) round($quantity / $initial * 100),
            'pakai' => $this->useOptions($row['unit'], $quantity, $initial),
            'satuan' => $row['unit'],
            'berat_gram' => $row['weight_grams'] !== null ? (float) $row['weight_grams'] : null,
            'harga' => $row['price'] !== null ? (int) $row['price'] : null,
            'ditambah_oleh' => $memberNames[$row['added_by'] ?? ''] ?? 'Mantan anggota',
            'tgl_beli' => $row['purchased_at'] ? Carbon::parse($row['purchased_at']) : null,
            'tgl_kedaluwarsa' => $expiresAt,
            'sisa_hari' => $expiresAt ? (int) Carbon::today('Asia/Jakarta')->diffInDays($expiresAt, false) : null,
            'sumber' => self::SOURCES[$row['input_source']] ?? 'Input manual',
            'status' => $row['status'],
        ];
    }

    /**
     * Tombol "pakai": pecahan dari jumlah awal untuk satuan takaran (¼ kotak susu = 250 ml dari 1000 ml),
     * atau 1 untuk satuan hitungan (1 butir telur). Pilihan yang menghabiskan sisa digantikan tombol "Habis".
     *
     * @return list<array{label: string, amount: float}>
     */
    private function useOptions(string $unit, float $quantity, float $initial): array
    {
        $fraction = fn (string $label, float $amount): array => [
            'label' => $label.' ('.str_replace('.', ',', (string) round($amount, 2)).' '.$unit.')',
            'amount' => round($amount, 2),
        ];

        $options = in_array($unit, self::MEASURED_UNITS, true)
            ? [$fraction('¼', $initial / 4), $fraction('½', $initial / 2), $fraction('¾', $initial * 3 / 4)]
            : [['label' => '1 '.$unit, 'amount' => 1.0]];

        return array_values(array_filter($options, fn (array $option): bool => $option['amount'] > 0 && $option['amount'] < $quantity));
    }
}
