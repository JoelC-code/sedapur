<?php

namespace App\Http\Controllers;

use App\Models\ReceiptScan;
use App\Jobs\ProcessReceiptScan;
use App\Services\SupabaseService;
use App\Support\SupabaseSession;
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
}
