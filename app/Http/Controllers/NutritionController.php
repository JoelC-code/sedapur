<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FatSecretService;
use Illuminate\Http\JsonResponse;

class NutritionController extends Controller
{
    protected FatSecretService $fatSecretService;
#   protected tkpiService $tkpiService;

    public function __construct(FatSecretService $fatSecretService)
    {
        $this->fatSecretService = $fatSecretService;
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $ingredientName = $request->query('query');

        $resultNutritionData = $this->fatSecretService->searchNutrition($ingredientName);

        if(!$resultNutritionData) {
            return response()->json([
                'status' => 'not_found',
                'title' => 'Bahan tidak ada',
                'message' => 'Jangan khawatir, kami sedang cari di sumber lain',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $resultNutritionData
        ]);
    }
}
