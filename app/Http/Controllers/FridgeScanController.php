<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Jobs\ProcessFridgeScan;
use App\Models\FridgeScan;
use App\Services\FoodItemService;
use App\Services\SupabaseService;
use App\Support\DemoData;
use App\Support\ReceiptPriceNormalizer;
use App\Support\SupabaseSession;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FridgeScanController extends Controller
{
    private function userId(): string
    {
        $id = SupabaseSession::user()['id'];

        abort_unless($id && Str::isUuid((string) $id), 401);

        return (string) $id;
    }

    private function householdId(): int
    {
        $id = (int) (SupabaseSession::household()['id'] ?? 0);

        abort_if($id <= 0, 401);

        return $id;
    }

    private function authorizeScan(FridgeScan $scan): void
    {
        abort_unless(
            $scan->user_id === $this->userId()
                && (int) $scan->household_id === $this->householdId(),
            403
        );
    }

    public function upload(Request $request, SupabaseService $supabase)
    {
        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120', // fridge-photos bucket limit: 5 MB
            ],
        ]);

        $accessToken = SupabaseSession::accessToken();
        $userId = $this->userId();
        $householdId = $this->householdId();
        $file = $request->file('photo');

        $scan = FridgeScan::create([
            'user_id' => $userId,
            'household_id' => $householdId,
            'status' => 'processing',
        ]);

        try {
            $path = $supabase->uploadHouseholdPhoto(
                $accessToken,
                'fridge-photos',
                $householdId,
                $file
            );

            $signedUrl = $supabase->createSignedUrl($accessToken, 'fridge-photos', $path, 3600);

            $scan->update(['image_path' => $path]);

            ProcessFridgeScan::dispatch($scan->id, $signedUrl);

            return response()->json([
                'message' => 'Fridge photo uploaded successfully',
                'redirect' => route('fridge-scan.processing', $scan),
            ]);
        } catch (\Throwable $e) {
            $scan->update([
                'status' => 'failed',
                'error_message' => 'Unable to upload fridge photo.',
            ]);

            Log::error('Fridge photo upload failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to upload fridge photo.',
                'redirect' => route('fridge-scan.failed', $scan),
            ], 500);
        }
    }

    public function processing(Request $request, FridgeScan $scan)
    {
        $this->authorizeScan($scan);

        return view('scan.processing', compact('scan'));
    }

    public function status(Request $request, FridgeScan $scan)
    {
        $this->authorizeScan($scan);

        return response()->json([
            'status' => $scan->status,
            'review_url' => route('fridge-scan.review', $scan),
            'failed_url' => route('fridge-scan.failed', $scan),
        ]);
    }

    public function review(Request $request, FridgeScan $scan)
    {
        $this->authorizeScan($scan);

        abort_unless($scan->status === 'completed', 404);

        $result = $scan->result ?? [];

        return view('scan.review', [
            'scan' => $scan,
            'result' => $result,
            'rows' => $result['items'] ?? [],
            'units' => FoodItemService::UNITS,
            'categories' => DemoData::CATEGORIES,
            'shelfDays' => FoodItemService::SHELF_DAYS,
        ]);
    }

    public function failed(Request $request, FridgeScan $scan)
    {
        $this->authorizeScan($scan);

        return view('scan.failed', compact('scan'));
    }

    public function save(Request $request, FridgeScan $scan, SupabaseService $supabase)
    {
        $this->authorizeScan($scan);

        abort_unless($scan->status === 'completed', 404);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.name' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit' => ['nullable', Rule::in(FoodItemService::UNITS)],
            'items.*.category' => ['nullable', Rule::in(array_keys(DemoData::CATEGORIES))],
            'items.*.weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.purchased_at' => ['nullable', 'date'],
            'items.*.expires_at' => ['nullable', 'date'],
        ]);

        $accessToken = SupabaseSession::accessToken();
        $householdId = $this->householdId();
        abort_if(! is_string($accessToken), 401);

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
            if ($quantity <= 0) {
                continue;
            }

            $unit = ReceiptPriceNormalizer::unit((string) ($submitted['unit'] ?? $original['unit'] ?? 'buah'));
            $category = (string) ($submitted['category'] ?? $original['category'] ?? 'lainnya');
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
                ])->withInput();
            }

            try {
                $inserted = $supabase->insert($accessToken, 'food_items', [
                    'household_id' => $householdId,
                    'name' => Str::limit($name, 120, ''),
                    'category' => $category,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'weight_grams' => $weight,
                    'price' => null, // no prices in a fridge photo
                    'purchased_at' => $purchasedAt,
                    'expires_at' => $expiresAt,
                    'input_source' => 'scan_kulkas',
                ]);

                if (! empty($inserted)) {
                    $saved++;
                } else {
                    $failures[] = $name;
                    Log::warning('Fridge item insert returned no row.', [
                        'scan_id' => $scan->id,
                        'item' => $name,
                        'household_id' => $householdId,
                    ]);
                }
            } catch (SupabaseException $e) {
                $failures[] = $name;
                Log::warning('Fridge item insert failed.', [
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

            return back()->withErrors(['items' => $detail])->withInput();
        }

        return redirect()->route('fridge.index')->with(
            'status',
            $saved.' bahan berhasil disimpan ke kulkas.'
        );
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
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
