<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiReceiptService
{
    public function extract(string $image, string $mimeType): array
    {
        $model = config('services.gemini.model');

        $prompt = <<<'PROMPT'
You are an Indonesian shopping receipt recognition system.

Read the receipt image and extract the purchased items.

Return JSON with this exact structure:

{
    "store_name": null,
    "purchase_date": null,
    "total": null,
    "items": [
        {
            "name": "BAYAM HIJAU",
            "quantity": 2,
            "unit": null,
            "unit_price": null,
            "total_price": 6000,
            "is_food": true
        }
    ]
}

Rules:

1. Extract every purchased item.
2. Preserve the original product name where possible.
3. Understand Indonesian receipt abbreviations.
4. Do not invent unreadable information.
5. Use null for unknown values.
6. Prices must be numeric, without currency symbols.
7. Distinguish food products from non-food products.
8. Do not treat the receipt's total as an item's price.
9. purchase_date must use YYYY-MM-DD.
10. Do not estimate nutritional values.
11. Return valid JSON only.
12. Do not calculate expiry dates.
PROMPT;

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

        return $result;
    }
}
