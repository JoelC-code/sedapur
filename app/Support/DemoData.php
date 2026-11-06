<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data contoh untuk tahap frontend.
 *
 * Bentuk datanya mengikuti ERD (Food_Items, Recipes, Meal_Plans, dst.)
 * supaya nanti cukup diganti dengan query ke Supabase tanpa mengubah view.
 */
class DemoData
{
    public const CATEGORIES = [
        'sayur' => ['label' => 'Sayur', 'emoji' => '🥬'],
        'buah' => ['label' => 'Buah', 'emoji' => '🍎'],
        'daging' => ['label' => 'Daging', 'emoji' => '🍗'],
        'ikan' => ['label' => 'Ikan', 'emoji' => '🐟'],
        'susu_telur' => ['label' => 'Susu & Telur', 'emoji' => '🥚'],
        'bumbu' => ['label' => 'Bumbu', 'emoji' => '🧂'],
        'kemasan' => ['label' => 'Kemasan', 'emoji' => '📦'],
        'lainnya' => ['label' => 'Lainnya', 'emoji' => '🍱'],
    ];

    public static function user(): array
    {
        return ['nama' => 'Hans Vere Liem', 'panggilan' => 'Hans', 'email' => 'hans@contoh.com', 'role' => 'owner'];
    }

    public static function household(): array
    {
        return [
            'nama' => 'Kos Melati Lt. 2',
            'kode_undangan' => 'MLT72K',
            'members' => [
                ['nama' => 'Hans', 'lengkap' => 'Hans (kamu)', 'role' => 'owner', 'joined_at' => '12 Okt 2026', 'warna' => 'bg-olive-700'],
                ['nama' => 'Grevi', 'lengkap' => 'Grevi', 'role' => 'member', 'joined_at' => '13 Okt 2026', 'warna' => 'bg-brand'],
                ['nama' => 'Joel', 'lengkap' => 'Joel', 'role' => 'member', 'joined_at' => '14 Okt 2026', 'warna' => 'bg-info'],
            ],
        ];
    }

    public static function foodItems(): Collection
    {
        $today = Carbon::today();

        $rows = [
            [1, 'Tahu putih', 'lainnya', 5, 'potong', 250, 5000, 'Hans', -1, 'Scan struk', '🧈'],
            [2, 'Bayam', 'sayur', 2, 'ikat', 300, 6000, 'Grevi', 1, 'Scan kulkas', '🥬'],
            [3, 'Susu UHT', 'susu_telur', 2, 'kotak', 1000, 18500, 'Joel', 2, 'Scan struk', '🥛'],
            [4, 'Tomat', 'sayur', 4, 'buah', 400, 7000, 'Grevi', 3, 'Scan kulkas', '🍅'],
            [5, 'Dada ayam', 'daging', 1, 'pack', 500, 32000, 'Joel', 5, 'Scan struk', '🍗'],
            [6, 'Wortel impor', 'sayur', 3, 'buah', 300, 9000, 'Grevi', 14, 'Scan struk', '🥕'],
            [7, 'Telur ayam', 'susu_telur', 6, 'butir', 360, 28000, 'Hans', 18, 'Input manual', '🥚'],
            [8, 'Beras', 'kemasan', 2, 'kg', 2000, 28000, 'Hans', 180, 'Scan struk', '🍚'],
            [9, 'Saus sambal', 'bumbu', 1, 'botol', 340, null, 'Grevi', 90, 'Input manual', '🌶️'],
        ];

        return collect($rows)->map(function ($r) use ($today) {
            [$id, $nama, $kategori, $jumlah, $satuan, $berat, $harga, $oleh, $sisaHari, $sumber, $emoji] = $r;

            return [
                'id' => $id,
                'nama' => $nama,
                'kategori' => $kategori,
                'emoji' => $emoji,
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'berat_gram' => $berat,
                'harga' => $harga,
                'ditambah_oleh' => $oleh,
                'tgl_beli' => $today->copy()->subDays(5),
                'tgl_kedaluwarsa' => $today->copy()->addDays($sisaHari),
                'sisa_hari' => $sisaHari,
                'sumber' => $sumber,
                'status' => 'tersedia',
                'gizi' => self::nutritionFor($nama),
            ];
        });
    }

    public static function foodItem(int $id): ?array
    {
        return self::foodItems()->firstWhere('id', $id);
    }

    /** Nilai gizi per 100 g beserta sumbernya (fatsecret / tkpi / estimasi_ai). */
    public static function nutritionFor(string $nama): array
    {
        $table = [
            'Tahu putih' => [80, 10.9, 0.8, 4.7, 'tkpi'],
            'Bayam' => [23, 2.9, 3.6, 0.4, 'fatsecret'],
            'Susu UHT' => [61, 3.2, 4.6, 3.3, 'fatsecret'],
            'Tomat' => [18, 0.9, 3.9, 0.2, 'fatsecret'],
            'Dada ayam' => [165, 31, 0, 3.6, 'fatsecret'],
            'Wortel impor' => [41, 0.9, 9.6, 0.2, 'estimasi_ai'],
            'Telur ayam' => [154, 12.4, 0.7, 10.8, 'tkpi'],
            'Beras' => [360, 6.8, 78.9, 0.7, 'tkpi'],
            'Saus sambal' => [93, 1.3, 20, 0.9, 'fatsecret'],
        ];

        [$kal, $pro, $karbo, $lemak, $sumber] = $table[$nama] ?? [0, 0, 0, 0, 'estimasi_ai'];

        return ['kalori' => $kal, 'protein' => $pro, 'karbo' => $karbo, 'lemak' => $lemak, 'sumber' => $sumber];
    }

    public static function recipes(): Collection
    {
        return collect([
            [
                'id' => 1, 'nama' => 'Telur dadar bayam tomat', 'emoji' => '🍳', 'bg' => 'bg-olive-100',
                'deskripsi' => 'Sarapan cepat untuk menghabiskan bayam dan tomat sebelum layu.',
                'porsi' => 2, 'menit' => 15, 'kalori' => 320, 'protein' => 18, 'karbo' => 9, 'lemak' => 22, 'dibuat_ai' => true,
                'bahan' => [
                    ['nama' => 'Telur ayam', 'berat' => '120 g', 'ada' => true, 'tag' => null],
                    ['nama' => 'Bayam', 'berat' => '100 g', 'ada' => true, 'tag' => 'Besok'],
                    ['nama' => 'Tomat', 'berat' => '80 g', 'ada' => true, 'tag' => '3 hari'],
                    ['nama' => 'Daun bawang', 'berat' => '10 g', 'ada' => false, 'tag' => null],
                ],
                'langkah' => [
                    'Cuci bayam, petik daunnya, lalu tiriskan.',
                    'Kocok 2 butir telur dengan sejumput garam.',
                    'Tumis tomat sebentar sampai layu.',
                    'Tuang telur, ratakan, lalu taburi bayam.',
                    'Taburi daun bawang, lipat, dan sajikan.',
                ],
            ],
            [
                'id' => 2, 'nama' => 'Tumis tahu tomat', 'emoji' => '🍲', 'bg' => 'bg-brand-soft',
                'deskripsi' => 'Pakai tahu yang sudah lewat tanggal sebelum benar-benar basi.',
                'porsi' => 2, 'menit' => 20, 'kalori' => 380, 'protein' => 21, 'karbo' => 14, 'lemak' => 24, 'dibuat_ai' => true,
                'bahan' => [
                    ['nama' => 'Tahu putih', 'berat' => '250 g', 'ada' => true, 'tag' => 'Lewat 1 hari'],
                    ['nama' => 'Tomat', 'berat' => '120 g', 'ada' => true, 'tag' => '3 hari'],
                    ['nama' => 'Saus sambal', 'berat' => '20 g', 'ada' => true, 'tag' => null],
                ],
                'langkah' => ['Potong dadu tahu lalu goreng sebentar.', 'Tumis tomat sampai hancur.', 'Masukkan tahu dan saus sambal, aduk rata.'],
            ],
            [
                'id' => 3, 'nama' => 'Sup ayam wortel', 'emoji' => '🍜', 'bg' => 'bg-warn-soft',
                'deskripsi' => 'Sup hangat dari dada ayam dan wortel impor.',
                'porsi' => 3, 'menit' => 40, 'kalori' => 410, 'protein' => 32, 'karbo' => 18, 'lemak' => 12, 'dibuat_ai' => false,
                'bahan' => [
                    ['nama' => 'Dada ayam', 'berat' => '300 g', 'ada' => true, 'tag' => '5 hari'],
                    ['nama' => 'Wortel impor', 'berat' => '200 g', 'ada' => true, 'tag' => null],
                ],
                'langkah' => ['Rebus ayam hingga matang.', 'Masukkan wortel, bumbui, masak 15 menit.'],
            ],
            [
                'id' => 4, 'nama' => 'Smoothie susu pisang', 'emoji' => '🥤', 'bg' => 'bg-ai-soft',
                'deskripsi' => 'Habiskan susu UHT yang tinggal 2 hari.',
                'porsi' => 2, 'menit' => 5, 'kalori' => 240, 'protein' => 8, 'karbo' => 38, 'lemak' => 6, 'dibuat_ai' => false,
                'bahan' => [
                    ['nama' => 'Susu UHT', 'berat' => '400 ml', 'ada' => true, 'tag' => '2 hari'],
                    ['nama' => 'Pisang', 'berat' => '2 buah', 'ada' => false, 'tag' => null],
                    ['nama' => 'Madu', 'berat' => '1 sdm', 'ada' => false, 'tag' => null],
                ],
                'langkah' => ['Masukkan semua bahan ke blender.', 'Blender sampai halus lalu sajikan dingin.'],
            ],
            [
                'id' => 5, 'nama' => 'Spaghetti tomat ayam', 'emoji' => '🍝', 'bg' => 'bg-danger-soft',
                'deskripsi' => 'Makan malam mengenyangkan dari stok ayam dan tomat.',
                'porsi' => 2, 'menit' => 30, 'kalori' => 510, 'protein' => 29, 'karbo' => 62, 'lemak' => 14, 'dibuat_ai' => false,
                'bahan' => [
                    ['nama' => 'Dada ayam', 'berat' => '200 g', 'ada' => true, 'tag' => null],
                    ['nama' => 'Tomat', 'berat' => '200 g', 'ada' => true, 'tag' => null],
                ],
                'langkah' => ['Rebus spaghetti.', 'Masak saus tomat dan ayam.', 'Campur dan sajikan.'],
            ],
        ]);
    }

    public static function recipe(int $id): ?array
    {
        return self::recipes()->firstWhere('id', $id);
    }

    public static function weekPlan(): array
    {
        $start = Carbon::today()->startOfWeek();
        $menus = [
            'pagi' => [['Nasi goreng telur', '🍳', 450], ['Roti isi telur', '🥪', 380], ['Oatmeal susu', '🥣', 310], ['Telur dadar bayam tomat', '🍳', 320, 1], ['Bubur ayam', '🍚', 360], ['Pancake pisang', '🥞', 420], ['Telur orak-arik', '🍳', 300]],
            'siang' => [['Soto ayam', '🍜', 520], ['Ayam kecap', '🍗', 610], ['Tumis kangkung tahu', '🥬', 390], ['Sup ayam wortel', '🍲', 410, 3], ['Spaghetti tomat ayam', '🍝', 510, 5], ['Nasi tim ayam', '🍚', 480], ['Ayam bakar', '🍗', 560]],
            'malam' => [['Capcay sayur', '🥗', 330], ['Sup wortel', '🥕', 280], ['Pepes ikan', '🐟', 360], ['Smoothie susu pisang', '🥤', 240, 4], ['Tumis tahu tomat', '🍲', 380, 2], ['Sayur sop', '🥣', 300], ['Gado-gado', '🥗', 420]],
        ];

        $days = [];
        foreach (range(0, 6) as $i) {
            $date = $start->copy()->addDays($i);
            $days[] = [
                'date' => $date,
                'is_today' => $date->isToday(),
                'meals' => collect($menus)->map(fn ($list) => [
                    'nama' => $list[$i][0], 'emoji' => $list[$i][1], 'kalori' => $list[$i][2], 'recipe_id' => $list[$i][3] ?? null,
                ])->all(),
            ];
        }

        return ['mulai' => $start, 'selesai' => $start->copy()->addDays(6), 'days' => $days];
    }

    public static function todayMenu(): array
    {
        $today = collect(self::weekPlan()['days'])->firstWhere('is_today', true) ?? self::weekPlan()['days'][0];

        return $today['meals'];
    }

    public static function shoppingList(): array
    {
        return [
            ['grup' => 'Dari resep: Telur dadar bayam tomat', 'items' => [['Daun bawang', '1 ikat', false]]],
            ['grup' => 'Dari resep: Smoothie susu pisang', 'items' => [['Pisang', '1 sisir', false], ['Madu', '1 botol kecil', false]]],
            ['grup' => 'Ditambah manual', 'items' => [['Beras', '5 kg', false]]],
            ['grup' => 'Sudah dibeli', 'items' => [['Minyak goreng', '1 L', true], ['Wortel', '500 g', true]]],
        ];
    }

    public static function activities(): array
    {
        return [
            ['hari' => 'Hari ini', 'items' => [
                ['Joel', 'menambahkan 6 bahan', 'Dari scan struk Superindo', 'ditambah', '10:24'],
                ['Grevi', 'memasak Bayam', 'Untuk resep telur dadar bayam', 'dimasak', '07:30'],
                ['Hans', 'membuang Tahu putih', 'Lewat 1 hari · Rp 5.000', 'dibuang', '07:12'],
            ]],
            ['hari' => 'Kemarin', 'items' => [
                ['Hans', 'mengubah Telur ayam', 'Jumlah 10 → 6 butir', 'diubah', '20:05'],
                ['Grevi', 'menambahkan Tomat', 'Input manual · 4 buah', 'ditambah', '17:40'],
            ]],
        ];
    }

    public static function nutrition(): array
    {
        return [
            'konsumsi' => [
                'hari_ini' => 1240, 'target' => 2000,
                'protein' => 52, 'karbo' => 148, 'lemak' => 46,
                'minggu' => [['Sen', 1820], ['Sel', 2050], ['Rab', 1690], ['Kam', 1240], ['Jum', 0], ['Sab', 0], ['Min', 0]],
                'log' => [
                    ['Telur dadar bayam tomat', '🍳', '1', '07:30', 320, 18, 9, 22],
                    ['Sup ayam wortel', '🍲', '1½', '12:10', 615, 48, 27, 18],
                    ['Smoothie susu pisang', '🥤', '1', '18:00', 305, 10, 49, 7],
                ],
            ],
            'kulkas' => [
                'total' => 18420, 'jumlah_bahan' => 24,
                'protein' => '1,1 kg', 'karbo' => '2,6 kg', 'lemak' => '640 g', 'berat' => '7,9 kg',
                'sumber' => ['fatsecret' => 14, 'tkpi' => 7, 'estimasi_ai' => 3],
                'penyumbang' => [['Beras', '🍚', '2.000 g', 7200], ['Spaghetti', '🍝', '500 g', 1855], ['Telur ayam', '🥚', '600 g', 924]],
            ],
        ];
    }

    public static function waste(): array
    {
        return [
            'bulan' => 'Oktober',
            'terbuang' => 38500,
            'perubahan' => -42,
            'bulan_lalu' => 66000,
            'dimasak' => 31,
            'dibuang' => 5,
            'terpakai' => 86,
            'riwayat' => [['Mei', 52000], ['Jun', 71000], ['Jul', 48000], ['Agu', 64000], ['Sep', 66000], ['Okt', 38500]],
            'terbanyak' => [['Susu UHT', '🥛', 1, 18500], ['Bayam', '🥬', 2, 12000], ['Tahu putih', '🧈', 1, 5000], ['Roti tawar', '🍞', 1, 3000]],
            'ringkasan' => 'Pemborosan turun 42%. Bayam dan susu masih paling sering terbuang karena belanja terlalu banyak sekaligus.',
            'saran' => ['Beli bayam 1 ikat per 2 hari.', 'Masukkan susu ke menu sarapan.'],
        ];
    }

    public static function scanResult(): array
    {
        return [
            ['Bayam', '🥬', 'sayur', 2, 'ikat', 6000, 'Besok', 'tkpi'],
            ['Susu UHT', '🥛', 'susu_telur', 2, 'kotak', 18500, '31 Okt', 'fatsecret'],
            ['Wortel impor', '🥕', 'sayur', 500, 'g', 9900, '12 Nov', 'estimasi_ai'],
            ['Kantong plastik', '🛍️', 'lainnya', 1, 'pcs', 200, '-', null],
        ];
    }

    public static function rupiah(?int $value): string
    {
        return $value === null ? '—' : 'Rp '.number_format($value, 0, ',', '.');
    }

    public static function number(int|float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /** Label & warna badge sisa umur bahan. Batas "hampir" = 3 hari (catatan desain). */
    public static function expiryBadge(int $sisaHari): array
    {
        return match (true) {
            $sisaHari < 0 => ['Lewat '.abs($sisaHari).' hari', 'bg-danger-soft text-danger'],
            $sisaHari === 0 => ['Hari ini', 'bg-danger-soft text-danger'],
            $sisaHari === 1 => ['Besok', 'bg-warn-soft text-warn'],
            $sisaHari <= 3 => [$sisaHari.' hari', 'bg-warn-soft text-warn'],
            $sisaHari <= 30 => [$sisaHari.' hari', 'bg-ok-soft text-olive-700'],
            default => [round($sisaHari / 30).' bulan', 'bg-sand-dark text-muted'],
        };
    }

    public static function sourceBadge(?string $sumber): array
    {
        return match ($sumber) {
            'fatsecret' => ['FatSecret', 'bg-info-soft text-info'],
            'tkpi' => ['TKPI', 'bg-ok-soft text-olive-700'],
            'estimasi_ai' => ['Estimasi AI', 'bg-ai-soft text-ai'],
            default => ['Tanpa gizi', 'bg-sand-dark text-muted'],
        };
    }
}
