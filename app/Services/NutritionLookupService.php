<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NutritionLookupService
{
    public function __construct(
        private FatSecretService $fatSecretService,
        private TkpiService $tkpiService,
    ) {}

    public function search(string $ingredientName): ?array 
    {
        try {
            $result = $this->fatSecretService->searchNutrition($ingredientName);

            if($result) {
                return [
                    'status' => 'success',
                    'data' => $result['data'] ?? $result,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('FatSecret lookup failed.', [
                'query' => $ingredientName,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $result = $this->tkpiService->searchNutrition($ingredientName);

            if ($result) {
                return [
                    'status' => 'success',
                    'data' => $result['data'] ?? $result,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('TKPI lookup failed.', [
                'query' => $ingredientName,
                'error' => $e->getMessage(),
            ]);
        }

        # No data, will implement AI fallback later
        return null;
    }

    /**
     * Cari nilai gizi yang bisa dihitung dengan berat dalam gram.
     *
     * @return array<string, mixed>|null
     */
    public function searchPer100g(string $ingredientName): ?array
    {
        $result = $this->search($ingredientName);
        $data = $result['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        if (($data['data_source'] ?? null) === 'tkpi') {
            return $data;
        }

        if (($data['data_source'] ?? null) !== 'fatsecret') {
            return null;
        }

        if (preg_match('/^100\s*g$/i', (string) ($data['metric_serving_unit'] ?? ''))) {
            return $data;
        }

        try {
            return $this->tkpiService->searchNutrition($ingredientName);
        } catch (\Throwable $e) {
            Log::warning('TKPI per-100g lookup failed.', [
                'query' => $ingredientName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
