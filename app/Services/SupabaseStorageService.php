<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SupabaseStorageService
{
    private function client(string $accessToken)
    {
        return Http::withHeaders([
            'apikey' => config('services.supabase.publishable_key'),
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
    }

    public function upload(
        string $contents,
        string $mimeType,
        string $userId,
        int $scanId,
        string $accessToken,
    ): string {
        $bucket = config('services.supabase.receipts_bucket');

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported image type'),
        };

        $path = "{$userId}/{$scanId}-" . Str::uuid() . ".{$extension}";

        $response = $this->client($accessToken)
            ->withHeaders([
                'Content-Type' => $mimeType,
                'x-upsert' => 'false',
            ])
            ->withBody($contents, $mimeType)
            ->post(
                config('services.supabase.url') .
                    "/storage/v1/object/{$bucket}/{$path}"
            );

        $response->throw();

        return $path;
    }

    public function createSignedURL(string $path, string $accessToken, int $expiresIn = 3600): string
    {
        $bucket = config('services.supabase.receipts_bucket');

        $response = $this->client($accessToken)
            ->post(
                config('services.supabase.url') .
                    "/storage/v1/object/sign/{$bucket}/{$path}",
                ['expiresIn' => $expiresIn]
            );

        $response->throw();

        $signedUrl = $response->json('signedURL');

        if (!$signedUrl) {
            throw new RuntimeException('Supabase did not return a signed URL');
        }
        if (!str_starts_with($signedUrl, 'http')) {
            $signedUrl = rtrim(config('services.supabase.url'), '/') .
                '/storage/v1' . $signedUrl;
        }

        return $signedUrl;
    }

    public function download(string $signedUrl): string {
        $response = Http::get($signedUrl);
        $response->throw();
        return $response->body();
    }
}
