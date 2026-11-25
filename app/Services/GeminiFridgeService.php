<?php

namespace App\Services;

use App\Support\DemoData;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiFridgeService
{
    public function extract(string $image, string $mimeType): array
    {
        $model = config('services.gemini.model');

        $categories = implode(', ', array_keys(DemoData::CATEGORIES));

        $prompt = str_replace('{{CATEGORIES}}', $categories, <<<'PROMPT'
         You are a food recognition system for Indonesian households.

Look at the photo of a refrigerator, pantry, or food storage and identify
every distinct food or drink item you can see.

Return JSON with this exact structure:

{
    "items": [
        {
            "name": "Telur Ayam",
            "quantity": 6,
            "unit": "butir",
            "unit_price": 0,
            "weight_grams": null,
            "category": "lainnya",
            "is_food": true
        }
    ]
}

Rules:

1. List each distinct item once. Group identical items (e.g. 6 eggs is one
   entry with quantity 6), do not repeat them.
2. Use Indonesian names, as commonly used in Indonesia
   (e.g. "Telur Ayam", "Susu UHT", "Bayam", "Cabai Merah").
   If a product has a readable brand or label, include it.
3. quantity is the number of units you can count. If you cannot tell,
   use 1. Never use 0 for quantity.
4. unit must be one of: g, kg, ml, l, butir, buah, ikat, bungkus, botol,
   kaleng, kotak. Choose the most natural one (eggs -> butir, leafy greens
   -> ikat, milk carton -> kotak, bottled drinks -> botol, packaged items ->
   bungkus). Use "buah" if unsure.
5. category must be exactly one of: {{CATEGORIES}}.
   Use "lainnya" if none fit.
6. weight_grams: only fill in if a weight or volume is clearly printed on a
   visible label. Otherwise use null. Do not guess.
7. unit_price is always 0 (prices cannot be seen in a fridge photo).
8. is_food is false for non-food things (containers, condiments' empty
   bottles, medicine, ice trays, etc.). Include them only if clearly
   visible, otherwise skip them.
9. Ignore items that are too blurry, hidden, or ambiguous to identify.
   Do not invent items.
10. Do not estimate nutritional values.
11. Do not calculate expiry dates or freshness.
12. Return valid JSON only.
PROMPT);

        $response = Http::timeout(120)
            ->withHeaders([
                'x-goog-api-key' => config('services.gemini.key'),
            ])
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => base64_encode($image),
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.1,
                    ],
                ]
            );

        $response->throw();

        $text = $response->json(
            'candidates.0.content.parts.0.text'
        );

        if (!is_string($text) || $text === '') {
            throw new RuntimeException(
                'Gemini returned an empty response.'
            );
        }

        $result = json_decode(
            $text,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            !is_array($result) ||
            !isset($result['items']) ||
            !is_array($result['items'])
        ) {
            throw new RuntimeException(
                'Invalid receipt JSON structure.'
            );
        }

        foreach ($result['items'] as &$item) {
            if (($item['quantity'] ?? 0) <= 0) {
                $item['quantity'] = 1;
            }
            if (($item['weight_grams'] ?? 0) <= 0) {
                $item['weight_grams'] = null;
            }
        }
        unset($item);

        return $result;
    }
}
