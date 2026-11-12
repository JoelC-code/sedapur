<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\JoinHouseholdRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Services\HouseholdService;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Buat rumah baru atau gabung lewat kode undangan.
 */
class HouseholdController extends Controller
{
    public function __construct(private HouseholdService $households) {}

    public function setup(): View
    {
        return view('household.setup', ['authUser' => SupabaseSession::user()]);
    }

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        try {
            $household = $this->households->create((string) SupabaseSession::accessToken(), $request->validated('name'));
        } catch (SupabaseException) {
            return back()->withInput()->withErrors(['name' => 'Rumah belum bisa dibuat. Coba lagi sebentar.']);
        }

        SupabaseSession::setHousehold($household);

        return redirect()->route('dashboard')->with('status', "Rumah {$household['name']} dibuat. Bagikan kode {$household['invite_code']} ke teman serumah.");
    }

    public function join(JoinHouseholdRequest $request): RedirectResponse
    {
        try {
            $household = $this->households->join(
                (string) SupabaseSession::accessToken(),
                (string) SupabaseSession::user()['id'],
                $request->validated('invite_code'),
            );
        } catch (SupabaseException $e) {
            $message = $e->errorCode === 'P0002'
                ? 'Kode undangan tidak ditemukan. Cek lagi ke pemilik rumah.'
                : 'Belum bisa bergabung. Coba lagi sebentar.';

            return back()->withErrors(['invite_code' => $message]);
        }

        SupabaseSession::setHousehold($household);

        return redirect()->route('dashboard')->with('status', "Kamu bergabung ke {$household['name']}.");
    }
}
