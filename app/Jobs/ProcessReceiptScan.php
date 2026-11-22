<?php

namespace App\Jobs;

use App\Models\ReceiptScan;
use App\Services\GeminiReceiptService;
use App\Services\NutritionLookupService;
use App\Support\ReceiptPriceNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessReceiptScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(
        public int $scanId,
        public string $signedUrl
    ) {}

    public function handle(
        GeminiReceiptService $gemini,
        NutritionLookupService $nutrition,
    ): void {
        $scan = ReceiptScan::findOrFail($this->scanId);

        $image = Http::timeout(30)->get($this->signedUrl)->throw()->body();

        $mimeType = match (pathinfo($scan->image_path, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new \RuntimeException('Unsupported image type'),
        };

        $result = $gemini->extract($image, $mimeType);

        if (empty($result['items'])) {
            throw new \RuntimeException('No receipt items detected.');
        }

        $result['total'] = ReceiptPriceNormalizer::receiptTotal($result['total'] ?? 0);

        foreach($result['items'] as &$item) {
            if(!is_array($item)) {
                continue;
            }

            $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1;
            $unitPrice = ReceiptPriceNormalizer::unitPrice($item['unit_price'] ?? 0);
            $item['quantity'] = $quantity;
            $item['unit'] = ReceiptPriceNormalizer::unit($item['unit'] ?? 'buah');
            $item['unit_price'] = $unitPrice;
            $item['total_price'] = ReceiptPriceNormalizer::lineTotal($quantity, $unitPrice);

            if (($item['is_food'] ?? false) !== true) {
                $item['nutrition'] = null;
                continue;
            }

            $ingredientName = trim(
                (string) ($item['name'] ?? '')
            );

            if ($ingredientName === '') {
                $item['nutrition'] = null;
                continue;
            }

            try {
                $item['nutrition'] = $nutrition->search(
                    $ingredientName
                );
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
        ReceiptScan::whereKey($this->scanId)
            ->update([
                'status' => 'failed',
                'error_message' => 'Receipt processing failed.',
            ]);
    }
}
