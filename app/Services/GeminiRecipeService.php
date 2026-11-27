<?php

namespace App\Services;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Membuat rekomendasi resep berbahasa Indonesia dari isi kulkas memakai Gemini API,
 * mendahulukan bahan yang paling cepat kedaluwarsa.
 */
class GeminiRecipeService
{
    private const UNIT_ALIASES = [
        'gram' => 'g',
        'gr' => 'g',
        'kilogram' => 'kg',
        'liter' => 'l',
        'milliliter' => 'ml',
        'mililiter' => 'ml',
        'pcs' => 'buah',
        'pc' => 'buah',
        'potong' => 'buah',
        'siung' => 'buah',
        'batang' => 'ikat',
        'lembar' => 'bungkus',
        'pack' => 'bungkus',
        'pak' => 'bungkus',
        'sachet' => 'bungkus',
        'sdm' => 'g',
        'sdt' => 'g',
    ];

    /**
     * @param  list<array<string, mixed>>  $fridgeItems
     * @return list<array<string, mixed>>
     */
    public function generate(array $fridgeItems): array
    {
        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if ($apiKey === '' || $model === '') {
            throw new RuntimeException('GEMINI_API_KEY belum diatur di server.');
        }

        $inventory = array_map(fn (array $item): array => [
            'nama' => $item['nama'],
            'jumlah' => $item['jumlah_angka'] ?? 1,
            'satuan' => $item['satuan'] ?? 'buah',
            'berat_gram' => $item['berat_gram'] ?? null,
            'sisa_hari' => $item['sisa_hari'] ?? null,
        ], array_slice($fridgeItems, 0, 25));

        $prompt = <<<'PROMPT'
Kamu adalah asisten dapur rumah tangga dan kos di Indonesia.

Berdasarkan daftar bahan kulkas berikut (sudah diurutkan dari paling cepat kedaluwarsa), buatkan 3 resep masakan rumahan Indonesia yang praktis dan realistis.

Kembalikan JSON valid dengan struktur persis:
{
    "recipes": [
        {
            "name": "Telur dadar bayam tomat",
            "description": "Sarapan cepat untuk menghabiskan bayam dan tomat sebelum layu.",
            "servings": 2,
            "cook_minutes": 15,
            "calories_per_serving": 320,
            "protein_per_serving": 18,
            "carbs_per_serving": 9,
            "fat_per_serving": 22,
            "ingredients": [
                {
                    "name": "Telur ayam",
                    "amount_text": "2 butir",
                    "quantity": 2,
                    "unit": "butir",
                    "grams": 120
                }
            ],
            "steps": [
                "Cuci bayam lalu tiriskan.",
                "Kocok telur bersama sedikit garam, lalu goreng hingga matang."
            ]
        }
    ]
}

Aturan:
1. Tulis semua teks dalam bahasa Indonesia yang singkat dan jelas.
2. Prioritaskan bahan dengan "sisa_hari" paling kecil (<= 3 hari atau negatif).
3. Gunakan nama bahan di "ingredients" yang konsisten dengan nama di daftar kulkas bila memakai bahan tersebut.
4. Boleh menambah 1-2 bahan pelengkap umum yang belum ada di kulkas jika memang diperlukan.
5. Kolom "unit" WAJIB salah satu dari: g, kg, ml, l, butir, buah, ikat, bungkus, botol, kaleng, kotak.
6. Kolom "quantity" harus angka > 0, "grams" perkiraan berat dalam gram (> 0), "servings" dan "cook_minutes" bilangan bulat > 0.
7. Perkiraan gizi (calories_per_serving, protein_per_serving, carbs_per_serving, fat_per_serving) per 1 porsi berupa angka >= 0.
8. Minimal 3 langkah memasak di "steps".
9. Kembalikan JSON saja tanpa markdown atau penjelasan tambahan.

Daftar bahan kulkas:
PROMPT;

        $response = Http::timeout(60)
            ->retry(
                [2000, 5000],
                throw: false,
                when: fn ($e): bool => $e instanceof RequestException
                    && in_array($e->getCode(), [429, 500, 503, 504], true),
            )
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [[
                    'parts' => [['text' => $prompt."\n".json_encode($inventory, JSON_UNESCAPED_UNICODE)]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.4,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini tidak dapat dihubungi saat ini.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini tidak mengembalikan resep.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! is_array($decoded['recipes'] ?? null)) {
            throw new RuntimeException('Format resep dari Gemini tidak valid.');
        }

        $recipes = [];

        foreach (array_slice($decoded['recipes'], 0, 3) as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $normalized = $this->normalizeRecipe($raw);

            if ($normalized !== null) {
                $recipes[] = $normalized;
            }
        }

        if ($recipes === []) {
            throw new RuntimeException('Gemini tidak menghasilkan resep yang valid.');
        }

        return $recipes;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalizeRecipe(array $raw): ?array
    {
        $name = Str::limit(trim((string) ($raw['name'] ?? '')), 120, '');
        $steps = array_values(array_filter(
            array_map(fn (mixed $s): string => trim((string) $s), is_array($raw['steps'] ?? null) ? $raw['steps'] : []),
            fn (string $s): bool => $s !== '',
        ));

        $ingredients = [];

        foreach (is_array($raw['ingredients'] ?? null) ? $raw['ingredients'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemName = Str::limit(trim((string) ($item['name'] ?? '')), 100, '');

            if ($itemName === '') {
                continue;
            }

            $unit = $this->normalizeUnit((string) ($item['unit'] ?? 'buah'));
            $quantity = is_numeric($item['quantity'] ?? null) && (float) $item['quantity'] > 0
                ? round((float) $item['quantity'], 2)
                : 1.0;
            $grams = is_numeric($item['grams'] ?? null) && (float) $item['grams'] > 0
                ? round((float) $item['grams'], 1)
                : ($unit === 'g' ? round($quantity, 1) : null);
            $amountText = Str::limit(
                trim((string) ($item['amount_text'] ?? '')),
                50,
                '',
            );

            if ($amountText === '') {
                $amountText = str_replace('.', ',', (string) $quantity).' '.$unit;
            }

            $ingredients[] = [
                'name' => $itemName,
                'amount_text' => $amountText,
                'quantity' => $quantity,
                'unit' => $unit,
                'grams' => $grams,
            ];
        }

        if ($name === '' || $steps === [] || $ingredients === []) {
            return null;
        }

        return [
            'name' => $name,
            'description' => Str::limit(trim((string) ($raw['description'] ?? '')), 400, ''),
            'servings' => max(1, min(20, (int) round((float) ($raw['servings'] ?? 2)))),
            'cook_minutes' => max(5, min(300, (int) round((float) ($raw['cook_minutes'] ?? 20)))),
            'calories_per_serving' => max(0.0, round((float) ($raw['calories_per_serving'] ?? 0), 1)),
            'protein_per_serving' => max(0.0, round((float) ($raw['protein_per_serving'] ?? 0), 1)),
            'carbs_per_serving' => max(0.0, round((float) ($raw['carbs_per_serving'] ?? 0), 1)),
            'fat_per_serving' => max(0.0, round((float) ($raw['fat_per_serving'] ?? 0), 1)),
            'steps' => array_slice($steps, 0, 15),
            'ingredients' => array_slice($ingredients, 0, 20),
        ];
    }

    private function normalizeUnit(string $unit): string
    {
        $clean = mb_strtolower(trim($unit));
        $mapped = self::UNIT_ALIASES[$clean] ?? $clean;

        return in_array($mapped, FoodItemService::UNITS, true) ? $mapped : 'buah';
    }
}
