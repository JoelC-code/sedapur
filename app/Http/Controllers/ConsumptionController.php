<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\StoreConsumptionRequest;
use App\Services\ConsumptionService;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;

class ConsumptionController extends Controller
{
    public function __construct(private ConsumptionService $consumption) {}

    public function store(StoreConsumptionRequest $request): RedirectResponse
    {
        $name = trim($request->validated('name'));

        try {
            $found = $this->consumption->log(
                (string) SupabaseSession::accessToken(),
                SupabaseSession::household()['id'],
                $name,
                (float) $request->validated('grams'),
            );
        } catch (SupabaseException) {
            return back()->withInput()->withErrors(['name' => 'Catatan belum tersimpan. Coba lagi sebentar lagi.']);
        }

        if (! $found) {
            return back()->withInput()->withErrors(['name' => "Data gizi \"{$name}\" belum ditemukan. Coba nama bahan yang lebih umum."]);
        }

        return to_route('nutrition.index')->with('status', "{$name} sudah dicatat.");
    }

    public function destroy(int $log): RedirectResponse
    {
        try {
            $deleted = $this->consumption->delete((string) SupabaseSession::accessToken(), $log);
        } catch (SupabaseException) {
            $deleted = false;
        }

        return to_route('nutrition.index')->with('status', $deleted ? 'Catatan dihapus.' : 'Catatan tidak ditemukan.');
    }
}
