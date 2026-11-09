<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class FatSecretService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $authUrl = 'https://oauth.fatsecret.com/connect/token';
    protected string $apiUrl = 'https://platform.fatsecret.com/rest/foods/search/v3';
    protected string $foodGetUrl = 'https://platform.fatsecret.com/rest/food/v';
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->clientId = config('services.fatsecret.client_id');
        $this->clientSecret = config('services.fatsecret.client_id');
    }

    public function getAccessToken(): string
    {
        return Cache::remember('fatsecret_access_token', 85000, function() {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'basic',
                ]);

                if($response->failed()) {
                    throw new Exception("Gagal autentikasi dengan FatSecret API");
                }

                return $response->json()['access_token'];
        });
    }

    public function searchNutrition(string $foodIngridientName): ?array
    {
        $accessToken = $this->getAccessToken();

        $searchResponse = Http::withToken($accessToken)
            ->get($this->apiUrl, [
                'search_expression' => $foodIngridientName,
                'format' => 'json',
                'max_results' => 1,
            ]);
        
            if($searchResponse->failed() || empty($searchResponse->json()['food_search']['results']['food'])) {
                return null;
            }

            $foodResults = $searchResponse->json()['foods_search']['results']['food'];
            $foodId = is_array($foodResults) && isset($foodResults[0])
                ? $foodResults[0]['food_id']
                : $foodResults['food_id'];
            
            $detailResponse = Http::withToken($accessToken)
                ->get($this->foodGetUrl, [
                    'food_id' => $foodId,
                    'format' => 'json',
                ]);

            if ($detailResponse->failed()) {
                return null;
            }

            $foodData = $detailResponse->json()['food'];

            return $this->formatNutritionData($foodData);
    }

    protected function formatNutritionData(array $foodData): array
    {
        $servings = $foodData['servings']['serving'];
        $serving = is_array($servings) && isset($servings[0]) ? $servings[0] : $servings;

        return [
            'name' => $foodData['food_name'],
            'calories' => (float) ($serving['calories'] ?? 0),
            'protein' => (float) ($serving['protein'] ?? 0),
            'carbohydrates' => (float) ($serving['carbohydrate'] ?? 0),
            'fat' => (float) ($serving['fat'] ?? 0),
            'metric_serving_amount' => $serving['metric_serving_amount'] ?? 100,
            'metric_serving_unit' => $serving['metric_serving_unit'] ?? 'g',
            'data_source' => 'fatsecret',
            'fatsecret_food_id' => $foodData['food_id'],
        ];
    }
}
