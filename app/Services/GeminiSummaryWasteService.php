<?php

namespace App\Services;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiSummaryWasteService
{
    public function summarize(array $stats): array
    {
        $model = config('services.gemini.model');

        $prompt = <<< 'PROMPT'
Kamu adalah asisten rumah tangga yang membantu mengurangi pemborosan makanan.

Berikut data bulanan sebuah rumah tangga dalam JSON. Nilai uang dalam rupiah.

Kembalikan JSON dengan struktur persis:

{
    "ringkasan": "...",
    "saran": ["...", "..."]
}

Aturan:
1. Tulis dalam bahasa Indonesia yang santai dan singkat.
2. "ringkasan" maksimal 2 kalimat, sebutkan perubahan dibanding bulan lalu dan bahan yang paling sering terbuang.
3. "saran" berisi 2 saran praktis, masing-masing maksimal 1 kalimat.
4. Hanya gunakan angka dan nama bahan yang ada di data. Jangan mengarang.
5. Jika perubahan bernilai positif, jangan menyebutnya membaik.
6. Kembalikan JSON saja.

Data:
PROMPT;
        $response = Http::timeout(60)
            ->retry(
                [2000, 5000],
                throw: false,
                when: fn($e) => $e instanceof RequestException
                    && in_array($e->getCode(), [429, 500, 503, 504], true),
            )
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [[
                    'parts' => [['text' => $prompt . "\n" . json_encode($stats, JSON_UNESCAPED_UNICODE)]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.4,
                ],
            ]);

        $response->throw();

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $result = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        if (! is_string($result['ringkasan'] ?? null) || ! is_array($result['saran'] ?? null)) {
            throw new RuntimeException('Invalid waste summary structure.');
        }

        return [
            'ringkasan' => trim($result['ringkasan']),
            'saran' => array_slice(array_values(array_filter(array_map('strval', $result['saran']))), 0, 3),
        ];
    }
}
