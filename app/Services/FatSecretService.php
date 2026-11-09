<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Support\Facades\Log;

class FatSecretService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $authUrl = 'https://oauth.fatsecret.com/connect/token';
    protected string $apiUrl = 'https://platform.fatsecret.com/rest/server.api';
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->clientId = (string) env('FATSECRET_CLIENT_ID', '');
        $this->clientSecret = (string) env('FATSECRET_CLIENT_SECRET', '');
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
        try {
        $accessToken = $this->getAccessToken();

        $searchResponse = Http::withToken($accessToken)
            ->get($this->apiUrl, [
                'method' => 'foods.search',
                'search_expression' => $foodIngridientName,
                'format' => 'json',
                'max_results' => 1,
            ]);

            // dd([
            //     'status' => $searchResponse->status(),
            //     'json' => $searchResponse->json(),
            //     'raw_body' => $searchResponse->body()
            // ]);

            $searchData = $searchResponse->json();
        
            if($searchResponse->failed() || empty($searchData['foods']['food'])) {
                return null;
            }

            $foodResults = $searchData['foods']['food'];

            $food = is_array($foodResults) && isset($foodResults[0]) ? $foodResults[0] : $foodResults;

            if (!$this->isValidMatching($foodIngridientName, $food)) {
                return null;
            }

            // dd([
            //     'hasil_akhir_food' => $food
            // ]);

            return $this->formatNutritionData($food);
        } catch (Exception $e) {
            Log::error("FatSecret Service Error: " . $e->getMessage());
            return null;
        }
    }

    protected function isValidMatching(string $query, array $food): bool 
    {
        $query = strtolower(trim($query));
        $foodName = strtolower($food['food_name'] ?? '');
        $brandName = strtolower($food['brand_name'] ?? '');

        if (str_contains($foodName, $query) || str_contains($query, $foodName)) {
            return true;
        }
        if ($brandName && (str_contains($brandName, $query) || str_contains($query, $brandName))) {
            return true;
        }
        $queryWords = array_filter(explode('', $query), fn($words) => strlen($words) > 2);

        foreach ($queryWords as $word) {
            if(str_contains($foodName, $word) || str_contains($brandName, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function formatNutritionData(array $foodData): array
    {
        $description = $foodData['food_description'] ?? '';
        
        preg_match('/Calories:\s*([\d\.]+)/i', $description, $calMatches);
        preg_match('/Protein:\s*([\d\.]+)/i', $description, $proMatches);
        preg_match('/Carbs:\s*([\d\.]+)/i', $description, $carbMatches);
        preg_match('/Fat:\s*([\d\.]+)/i', $description, $fatMatches);
        preg_match('/Per\s+(.*?)\s+-/i', $description, $servingMatches);

        return [
            'name' => $foodData['food_name'] ?? 'Unknown',
            'calories' => (float) ($calMatches[1] ?? 0),
            'protein' => (float) ($proMatches[1] ?? 0),
            'carbohydrates' => (float) ($carbMatches[1] ?? 0),
            'fat' => (float) ($fatMatches[1] ?? 0),
            'metric_serving_amount' => 100, // Secara default API mengembalikan basis gram/takaran yang tertera
            'metric_serving_unit' => trim($servingMatches[1] ?? 'g'),
            'data_source' => 'fatsecret',
        ];
    }
}
