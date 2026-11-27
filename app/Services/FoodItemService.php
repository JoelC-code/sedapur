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
            'jumlah_awal_angka' => $initial,
            'sisa_persen' => (int) round($quantity / $initial * 100),
            'pakai' => $this->useOptions($row['unit'], $quantity, $initial),
            'slider' => $this->useSlider($row['unit'], $quantity, $initial, $row['weight_grams'] !== null ? (float) $row['weight_grams'] : null),
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
     * Pecahan yang hasilnya tidak bulat (¼ dari 330 ml = 82,5 ml) tidak dijadikan tombol; pakai slider.
     *
     * @return list<array{label: string, amount: float}>
     */
    private function useOptions(string $unit, float $quantity, float $initial): array
    {
        // Bulat dalam satuan terkecil: 0,25 kg = 250 g bulat, 82,5 ml tidak.
        $baseFactor = in_array($unit, ['kg', 'l'], true) ? 1000 : 1;
        $isRound = fn (float $amount): bool => abs($amount * $baseFactor - round($amount * $baseFactor)) < 0.001;

        $fraction = fn (string $label, float $amount): array => [
            'label' => $label.' ('.str_replace('.', ',', (string) round($amount, 2)).' '.$unit.')',
            'amount' => round($amount, 2),
        ];

        $options = in_array($unit, self::MEASURED_UNITS, true)
            ? [$fraction('¼', $initial / 4), $fraction('½', $initial / 2), $fraction('¾', $initial * 3 / 4)]
            : [['label' => '1 '.$unit, 'amount' => 1.0]];

        return array_values(array_filter($options, fn (array $option): bool => $option['amount'] > 0
            && $option['amount'] < $quantity
            && $isRound($option['amount'])));
    }

    /**
     * Slider "pakai": dari satu langkah sampai seluruh sisa (sisa penuh = habis).
     * Langkah 1 g/ml, 0,01 kg/l, 1 butir (telur tidak dipakai setengah), atau 0,05 untuk satuan
     * hitungan lain, supaya "1 kotak susu" bisa dipakai sebagian. Bila berat bahan diisi,
     * gram per satuan ikut dikirim agar label slider menampilkan perkiraan gram.
     *
     * @return array{min: float, max: float, step: float, value: float, grams_per_unit: float|null}|null
     */
    private function useSlider(string $unit, float $quantity, float $initial, ?float $weightGrams): ?array
    {
        $step = match (true) {
            in_array($unit, ['g', 'ml'], true), $unit === 'butir' => 1.0,
            in_array($unit, ['kg', 'l'], true) => 0.01,
            default => 0.05,
        };

        if ($quantity <= $step) {
            return null;
        }

        return [
            'min' => $step,
            'max' => $quantity,
            'step' => $step,
            'value' => max($step, round($quantity / 2 / $step) * $step),
            'grams_per_unit' => $weightGrams && ! in_array($unit, self::MEASURED_UNITS, true) ? $weightGrams / $initial : null,
        ];
    }

    /**
    * Tampilan AI summary untuk makanan
    *
    * @return array{select: string, household_id: float, status: bool, and: string}
    */ 
    public function finished(string $accessToken, int $householdId, Carbon $from, Carbon $to): array
    {
        return $this->supabase->select($accessToken, 'food_items', [
            'select' => 'name,category,quantity,initial_quantity,price,status,status_changed_at',
            'household_id' => "eq.{$householdId}",
            'status' => 'in.(dimasak,dibuang)',
            'and' => '(status_changed_at.gte.'.$from->copy()->utc()->format('Y-m-d\TH:i:s\Z')
                .',status_changed_at.lt.'.$to->copy()->utc()->format('Y-m-d\TH:i:s\Z').')',
        ]);
    }
}
