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
        $this->clientId = (string) config('services.fatsecret.client_id');
        $this->clientSecret = (string) config('services.fatsecret.client_secret');
    }

    public function getAccessToken(): string
    {
        return Cache::remember('fatsecret_access_token', 85000, function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'basic',
                ]);

            if ($response->failed()) {
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
                    'max_results' => 10,
                ]);

            // dd([
            //     'status' => $searchResponse->status(),
            //     'json' => $searchResponse->json(),
            //     'raw_body' => $searchResponse->body()
            // ]);

            $searchData = $searchResponse->json();

            if ($searchResponse->failed() || empty($searchData['foods']['food'])) {
                return null;
            }

            $foodResults = $searchData['foods']['food'];
            $foods = isset($foodResults['food_id']) ? [$foodResults] : $foodResults;

            $bestResult = $this->pickBestMatch($foodIngridientName, $foods);
            if (!$bestResult) {
                return null;
            }

            // dd([
            //     'hasil_akhir_food' => $food
            // ]);

            return $this->formatNutritionData($bestResult);
        } catch (Exception $e) {
            Log::error("FatSecret Service Error: " . $e->getMessage());
            return null;
        }
    }

    protected function pickBestMatch(string $query, array $foods): ?array
    {
        $query = mb_strtolower(trim($query));
        $queryWords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $best = null;
        $bestScore = 0;

        foreach ($foods as $food) {
            $name = mb_strtolower($food['food_name'] ?? '');
            $nameWords = preg_split('/[\s,\-]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
            $score = 0;

            if ($name === $query) {
                $score += 100;                       // exact match
            }

            $score += 20 * count(array_intersect($queryWords, $nameWords));

            if(($food['food_type'] ?? '') === 'Generic') {
                $score += 15;
            }

            $score -= count($nameWords);

            if($score > $bestScore) {
                $bestScore = $score;
                $best = $food;
            }
        }

        return $bestScore >= 20 ? $best : null;
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
