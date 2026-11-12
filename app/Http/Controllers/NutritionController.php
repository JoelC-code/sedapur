<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FatSecretService;
use App\Services\TkpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class NutritionController extends Controller
{
    protected FatSecretService $fatSecretService;
    protected TkpiService $tkpiService;

    public function __construct(FatSecretService $fatSecretService, TkpiService $tkpiService)
    {
        $this->fatSecretService = $fatSecretService;
        $this->tkpiService = $tkpiService;
    }

    public function search(Request $request): JsonResponse
    {
        $ingredientName = $request->input('query');
        if (!$ingredientName) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter query nama bahan (query) wajib diisi.'
            ], 400);
        }

        try {
            $fatSecretResult = $this->fatSecretService->searchNutrition($ingredientName);
            if ($fatSecretResult) {
                return response()->json([
                    'status' => 'success',
                    'data' => $fatSecretResult
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FatSecret lookup failed; falling back to TKPI.', [
                'query' => $ingredientName,
                'error' => $e->getMessage(),
            ]);
        }

        try {
        $tkpiResult = $this->tkpiService->searchNutrition($ingredientName);
        if($tkpiResult) {
            return response()->json([
                'status' => 'success',
                'data' => $tkpiResult
            ]);
        }
        } catch (Throwable $e) {
            Log::warning('Error', [
                'query' => $ingredientName,
                'error' => $e->getMessage()
            ]);
        }

        # Fallback dibawahnya, yang buat AI nanti ganti bagian ini sampai bawah.
        $aiFallbackData = [
            'name' => $ingredientName, // Gunakan string $ingredientName, bukan object $request
            'calories' => 0,
            'protein' => 0,
            'carbohydrates' => 0,
            'fat' => 0,
            'data_source' => 'pending_ai_estimation',
            'fatsecret_food_id' => null,
        ];

        return response()->json([
            'status' => 'success_with_fallback',
            'message' => 'Data tidak ditemukan di FatSecret maupun TKPI. Menunggu AI estimation.',
            'data' => $aiFallbackData
        ]);
    }
}