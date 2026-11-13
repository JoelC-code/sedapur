<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\JoinHouseholdRequest;
use App\Http\Requests\LeaveHouseholdRequest;
use App\Http\Requests\RemoveMemberRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Services\HouseholdService;
use App\Support\HouseholdMembers;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buat rumah baru atau gabung lewat kode undangan.
 */
class HouseholdController extends Controller
{
    public function __construct(private HouseholdService $households) {}

    public function setup(Request $request): View
    {
        // Kode dari link undangan (?kode=ARFRG7) mengisi kotak kode; yang formatnya salah diabaikan.
        $code = strtoupper((string) $request->query('kode', ''));

        return view('household.setup', [
            'authUser' => SupabaseSession::user(),
            'inviteCode' => preg_match('/^[A-HJKMNP-Z2-9]{6}$/', $code) ? $code : '',
        ]);
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
        HouseholdMembers::forget($household['id']);

        return redirect()->route('dashboard')->with('status', "Kamu bergabung ke {$household['name']}.");
    }

    public function leave(LeaveHouseholdRequest $request): RedirectResponse
    {
        $household = SupabaseSession::household();

        try {
            $this->households->leave((string) SupabaseSession::accessToken(), $household['id'], (string) SupabaseSession::user()['id']);
        } catch (SupabaseException) {
            return back()->with('status', 'Belum bisa keluar dari rumah. Coba lagi sebentar.');
        }

        HouseholdMembers::forget($household['id']);
        SupabaseSession::forgetHousehold();

        return redirect()->route('household.setup')->with('status', "Kamu sudah keluar dari {$household['name']}.");
    }

    public function removeMember(RemoveMemberRequest $request, string $userId): RedirectResponse
    {
        $household = SupabaseSession::household();

        try {
            $this->households->removeMember((string) SupabaseSession::accessToken(), $household['id'], $userId);
        } catch (SupabaseException) {
            return back()->with('status', 'Anggota belum bisa dikeluarkan. Coba lagi sebentar.');
        }

        HouseholdMembers::forget($household['id']);

        return back()->with('status', 'Anggota sudah dikeluarkan dari rumah.');
    }
}
