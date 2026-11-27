<?php

namespace App\Jobs;

use App\Models\FridgeScan;
use App\Services\GeminiFridgeService;
use App\Services\NutritionLookupService;
use App\Support\DemoData;
use App\Support\ReceiptPriceNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFridgeScan implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $timeout = 150;

    public function __construct(
        public int $scanId,
        public string $signedUrl
    ) {}

public function handle(
        GeminiFridgeService $gemini,
        NutritionLookupService $nutrition,
    ): void {
        $scan = FridgeScan::findOrFail($this->scanId);

        $image = Http::timeout(30)->get($this->signedUrl)->throw()->body();

        $mimeType = match (strtolower(pathinfo($scan->image_path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new \RuntimeException('Unsupported image type'),
        };

        $result = $gemini->extract($image, $mimeType);

        if (empty($result['items'])) {
            throw new \RuntimeException('No fridge items detected.');
        }

        foreach ($result['items'] as &$item) {
            if (! is_array($item)) {
                continue;
            }

            // Quantity must be > 0 (save() validates gt:0)
            $quantity = is_numeric($item['quantity'] ?? null) && (float) $item['quantity'] > 0
                ? (float) $item['quantity']
                : 1;

            // Weight must be null or > 0
            $weight = is_numeric($item['weight_grams'] ?? null) && (float) $item['weight_grams'] > 0
                ? (float) $item['weight_grams']
                : null;

            // Category must be a real key, otherwise fall back
            $category = (string) ($item['category'] ?? 'lainnya');
            if (! array_key_exists($category, DemoData::CATEGORIES)) {
                $category = 'lainnya';
            }

            $item['quantity'] = $quantity;
            $item['unit'] = ReceiptPriceNormalizer::unit($item['unit'] ?? 'buah');
            $item['unit_price'] = 0;     // no prices in a fridge photo
            $item['total_price'] = 0;
            $item['weight_grams'] = $weight;
            $item['category'] = $category;
            $item['is_food'] = ($item['is_food'] ?? true) === true;

            if (! $item['is_food']) {
                $item['nutrition'] = null;
                continue;
            }

            $ingredientName = trim((string) ($item['name'] ?? ''));

            if ($ingredientName === '') {
                $item['nutrition'] = null;
                continue;
            }

            try {
                $item['nutrition'] = $nutrition->search($ingredientName);
            } catch (Throwable $e) {
                Log::warning('Nutrition lookup failed.', [
                    'scan_id' => $scan->id,
                    'ingredient' => $ingredientName,
                    'error' => $e->getMessage(),
                ]);

                $item['nutrition'] = null;
            }
        }

        unset($item);

        $scan->update([
            'status' => 'completed',
            'result' => $result,
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        FridgeScan::whereKey($this->scanId)
            ->update([
                'status' => 'failed',
                'error_message' => 'Fridge processing failed.',
            ]);
    }
}

