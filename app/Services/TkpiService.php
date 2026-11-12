<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TkpiService
{
    /** Skor minimal word_similarity (pg_trgm) agar salah ketik seperti "bayem" tetap cocok. */
    private const MIN_WORD_SIMILARITY = 0.5;

    /**
     * Kata umum yang di TKPI punya banyak varian. Tanpa ini "telur" bisa jatuh ke telur penyu.
     *
     * @var array<string, string>
     */
    private const COMMON_NAMES = [
        'telur' => 'telur ayam ras, segar',
        'telor' => 'telur ayam ras, segar',
        'telur ayam' => 'telur ayam ras, segar',
        'susu' => 'susu sapi, segar',
        'ayam' => 'ayam, daging, segar',
        'daging ayam' => 'ayam, daging, segar',
        'daging sapi' => 'sapi, daging, lemak sedang, segar',
        'sapi' => 'sapi, daging, lemak sedang, segar',
    ];

    /**
     * Cari nilai gizi per 100 g di tabel TKPI.
     *
     * Urutan prioritas: nama yang diawali kata pencarian, bahan segar/mentah (isi kulkas),
     * kemiripan kata tertinggi, lalu nama terpendek (paling umum).
     *
     * @return array{name: string, calories: float, protein: float, carbohydrates: float, fat: float, bdd: float, metric_serving_amount: int, metric_serving_unit: string, data_source: string}|null
     */
    public function searchNutrition(string $ingredientName): ?array
    {
        $search = mb_strtolower(trim($ingredientName));

        if ($search === '') {
            return null;
        }

        $item = $this->bestMatch(self::COMMON_NAMES[$search] ?? $search);

        if (! $item) {
            return null;
        }

        return [
            'name' => $item->nama_bahan,
            'calories' => (float) $item->kalori_100g,
            'protein' => (float) $item->protein_100g,
            'carbohydrates' => (float) $item->karbohidrat_100g,
            'fat' => (float) $item->lemak_100g,
            'bdd' => (float) $item->bdd,
            'metric_serving_amount' => 100,
            'metric_serving_unit' => 'g',
            'data_source' => 'tkpi',
        ];
    }

    private function bestMatch(string $search): ?object
    {
        $isPostgres = DB::getDriverName() === 'pgsql';
        $like = $isPostgres ? 'ilike' : 'like';
        $escaped = addcslashes($search, '\\%_');

        $query = DB::table('tkpi_items');

        // Setiap kata harus cocok: "ikan lele" tidak boleh jatuh ke "Ikan lehoma" hanya karena kata "ikan".
        foreach (preg_split('/\s+/', $search) as $word) {
            $query->where(function (Builder $query) use ($isPostgres, $like, $word): void {
                $query->whereRaw("nama_bahan {$like} ? escape '\\'", ['%'.addcslashes($word, '\\%_').'%']);

                if ($isPostgres) {
                    $query->orWhereRaw('word_similarity(?, nama_bahan) >= ?', [$word, self::MIN_WORD_SIMILARITY]);
                }
            });
        }

        return $query
            ->orderByRaw("case when nama_bahan {$like} ? escape '\\' then 0 else 1 end", ["{$escaped}%"])
            ->orderByRaw("case when nama_bahan {$like} '%segar%' or nama_bahan {$like} '%mentah%' then 0 else 1 end")
            ->when($isPostgres, fn (Builder $query) => $query->orderByRaw('word_similarity(?, nama_bahan) desc', [$search]))
            ->orderByRaw('length(nama_bahan)')
            ->first();
    }
}
