<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TkpiService
{
    public function searchNutrition(string $ingredientName): ?array
    {
        $normalizeSearch = strtolower(trim($ingredientName));

        # Operator ILIKE (utama)
        $item = DB::table('tkpi_items')
            ->where('nama_bahan', 'LIKE', "%{$normalizeSearch}%")
            ->first();

        if (!$item && DB::getDriverName() === 'pgsql') {
            $item = DB::table('tkpi_items')
                ->select('*')
                ->selectRaw('similarity(nama_bahan, ?) as match_score', [$normalizeSearch])
                ->whereRaw('similarity(nama_bahan, ?) > 0.25', [$normalizeSearch])
                ->orderByDesc('match_score')
                ->first();
        }

        if (!$item) {
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
}
