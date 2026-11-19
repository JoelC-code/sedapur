<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Models\ReceiptScan;
use App\Jobs\ProcessReceiptScan;
use App\Services\SupabaseService;
use App\Support\SupabaseSession;
use App\Support\ReceiptPriceNormalizer;
use App\Services\FoodItemService;
use App\Support\DemoData;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReceiptScanController extends Controller
{
    private function userId(): string
    {
        $id = SupabaseSession::user()['id'];

        abort_unless($id && Str::isUuid((string) $id), 401);

        return (string) $id;
    }

    public function upload(
        Request $request,
        SupabaseService $supabase
    ) {
        $request->validate([
            'receipt' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120', // batas bucket receipts: 5 MB
            ],
        ]);

        $accessToken = SupabaseSession::accessToken();

        $userId = $this->userId();

        $file = $request->file('receipt');

        // Create a scan record first so its ID can be
        // included in the Supabase Storage path.
        $scan = ReceiptScan::create([
            'user_id' => $userId,
            'status' => 'processing',
        ]);

        try {
            $path = $supabase->uploadHouseholdPhoto(
                $accessToken,
                'receipts',
                (int) SupabaseSession::household()['id'],
                $file
            );

            // 1 jam: job antrean bisa tertunda dan dicoba ulang sebelum mengunduh foto.
            $signedUrl = $supabase->createSignedUrl($accessToken, 'receipts', $path, 3600);

            $scan->update([
                'image_path' => $path,
            ]);

            ProcessReceiptScan::dispatch($scan->id, $signedUrl);

            return response()->json([
                'message' => 'Receipt Uploaded Successfully',
                'redirect' => route('scan.processing', $scan),
            ]);
        } catch (\Throwable $e) {

            $scan->update([
                'status' => 'failed',
                'error_message' => 'Unable to upload receipt.',
            ]);

            Log::error('Receipt upload failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to upload receipt.',
                'redirect' => route('scan.failed', $scan),
            ], 500);
        }
    }

    public function processing(
        Request $request,
        ReceiptScan $scan
    ) {
        abort_unless(
            $scan->user_id === $this->userId(),
            403
        );

        return view('scan.processing', compact('scan'));
    }

    public function status(
        Request $request,
        ReceiptScan $scan
    ) {
        abort_unless(
            $scan->user_id === $this->userId(),
            403
        );

        return response()->json([
            'status' => $scan->status,
            'review_url' => route('scan.review', $scan),
            'failed_url' => route('scan.failed', $scan),
        ]);
    }

    public function review(
        Request $request,
        ReceiptScan $scan
    ) {
        abort_unless(
            $scan->user_id === $this->userId(),
            403
        );

        abort_unless($scan->status === 'completed', 404);

        $result = $scan->result ?? [];

        $rows = $result['items'] ?? [];

        return view('scan.review', compact(
            'scan',
            'result',
            'rows'
        ));
    }

    public function failed(
        Request $request,
        ReceiptScan $scan
    ) {
        abort_unless(
            $scan->user_id === $this->userId(),
            403
        );

        return view('scan.failed', compact('scan'));
    }

    public function save(
        Request $request,
        ReceiptScan $scan,
        SupabaseService $supabase
    ) {
        abort_unless(
            $scan->user_id === $this->userId(),
            403
        );

        abort_unless($scan->status === 'completed', 404);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.selected' => ['nullable', 'boolean'],   // <-- add this
            'items.*.name' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'items.*.unit' => ['nullable', Rule::in(FoodItemService::UNITS)],
            'items.*.category' => ['nullable', Rule::in(array_keys(DemoData::CATEGORIES))],
            'items.*.weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.purchased_at' => ['nullable', 'date'],
            'items.*.expires_at' => ['nullable', 'date'],
        ]);

        $accessToken = SupabaseSession::accessToken();
        $householdId = (int) (SupabaseSession::household()['id'] ?? 0);
        abort_if(! is_string($accessToken) || $householdId <= 0, 401);
        $rows = $scan->result['items'] ?? [];
        $saved = 0;
        $failures = [];

        foreach ($validated['items'] as $index => $submitted) {
            if (! is_array($submitted) || ! isset($submitted['selected'])) {
                continue;
            }

            $original = $rows[$index] ?? null;
            if (! is_array($original) || ! $this->isFood($original['is_food'] ?? false)) {
                continue;
            }

            $name = trim((string) ($submitted['name'] ?? $original['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $quantity = (float) ($submitted['quantity'] ?? $original['quantity'] ?? 1);
            $unitPrice = ReceiptPriceNormalizer::unitPrice(
                $submitted['unit_price'] ?? $original['unit_price'] ?? 0
            );
            $price = ReceiptPriceNormalizer::lineTotal($quantity, $unitPrice);
            $unit = $this->normalizeUnit((string) ($submitted['unit'] ?? $original['unit'] ?? 'buah'));
            $category = (string) ($submitted['category'] ?? 'lainnya');
            $category = array_key_exists($category, DemoData::CATEGORIES) ? $category : 'lainnya';
            $weight = is_numeric($submitted['weight_grams'] ?? null)
                ? (float) $submitted['weight_grams']
                : null;
            $purchasedAt = $this->validDate(
                $submitted['purchased_at'] ?? now('Asia/Jakarta')->toDateString()
            );
            $expiresAt = $this->validDate($submitted['expires_at'] ?? null);

            if ($expiresAt && $purchasedAt && $expiresAt < $purchasedAt) {
                return back()->withErrors([
                    'items' => 'Tanggal kedaluwarsa tidak boleh sebelum tanggal beli.',
                ]);
            }

            if ($quantity <= 0) {
                continue;
            }

            try {
                $inserted = $supabase->insert($accessToken, 'food_items', [
                    'household_id' => $householdId,
                    'name' => Str::limit($name, 120, ''),
                    'category' => $category,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'weight_grams' => $weight,
                    'price' => $price > 0 ? $price : null,
                    'purchased_at' => $purchasedAt,
                    'expires_at' => $expiresAt,
                    'input_source' => 'scan_struk',
                ]);

                if (! empty($inserted)) {
                    $saved++;
                } else {
                    $failures[] = $name;
                    Log::warning('Receipt item insert returned no row.', [
                        'scan_id' => $scan->id,
                        'item' => $name,
                        'household_id' => $householdId,
                    ]);
                }
            } catch (SupabaseException $e) {
                $failures[] = $name;
                Log::warning('Receipt item insert failed.', [
                    'scan_id' => $scan->id,
                    'item' => $name,
                    'household_id' => $householdId,
                    'error_code' => $e->errorCode,
                    'status' => $e->status,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($saved === 0) {
            $detail = $failures === []
                ? 'Pilih setidaknya satu bahan makanan untuk disimpan.'
                : 'Bahan tidak dapat disimpan. Periksa kembali kategori, satuan, dan tanggal.';

            return back()->withErrors([
                'items' => $detail,
            ]);
        }

        return redirect()->route('fridge.index')->with(
            'success',
            $saved . ' bahan berhasil disimpan ke kulkas.'
        );
    }

    private function normalizeUnit(string $unit): string
    {
        return ReceiptPriceNormalizer::unit($unit);
    }

    private function isFood(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function validDate(mixed $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
