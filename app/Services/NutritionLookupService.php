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
}
