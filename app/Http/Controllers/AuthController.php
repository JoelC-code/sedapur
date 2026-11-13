<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\SupabaseService;
use App\Support\SupabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Masuk, daftar dan keluar memakai Supabase Auth (email + kata sandi).
 */
class AuthController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            $session = $this->supabase->signInWithPassword($request->validated('email'), $request->validated('password'));
            SupabaseSession::store($session);
        } catch (SupabaseException $e) {
            return back()->withInput($request->only('email'))->withErrors(['email' => $this->message($e)]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            $result = $this->supabase->signUp($request->validated('email'), $request->validated('password'), $request->validated('name'));
        } catch (SupabaseException $e) {
            return back()->withInput($request->only('name', 'email'))->withErrors(['email' => $this->message($e)]);
        }

        // Konfirmasi email aktif: Supabase belum memberi session sampai link di email dibuka.
        if (! isset($result['access_token'])) {
            return redirect()->route('login')->with('status', 'Akun dibuat. Buka link konfirmasi yang dikirim ke '.$request->validated('email').', lalu masuk.');
        }

        try {
            SupabaseSession::store($result);
        } catch (SupabaseException $e) {
            return back()->withInput($request->only('name', 'email'))->withErrors(['email' => $this->message($e)]);
        }

        $request->session()->regenerate();

        // Kembali ke link undangan yang tadi dibuka (mis. /rumah/mulai?kode=ARFRG7) bila ada.
        return redirect()->intended(route('household.setup'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $accessToken = SupabaseSession::accessToken();

        if ($accessToken !== null) {
            try {
                $this->supabase->signOut($accessToken);
            } catch (SupabaseException) {
                // Session di Supabase mungkin sudah tidak ada; session lokal tetap dihapus.
            }
        }

        SupabaseSession::clear();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Kamu sudah keluar.');
    }

    private function message(SupabaseException $e): string
    {
        return match ($e->errorCode) {
            'invalid_credentials' => 'Email atau kata sandi salah.',
            'email_not_confirmed' => 'Email belum dikonfirmasi. Buka link di email kamu dulu.',
            'user_already_exists', 'email_exists' => 'Email ini sudah terdaftar. Silakan masuk.',
            'weak_password' => 'Kata sandi terlalu lemah. Pakai kombinasi huruf dan angka yang lebih panjang.',
            'email_address_invalid' => 'Alamat email ini tidak bisa dipakai. Gunakan email lain.',
            'email_address_not_authorized' => 'Email konfirmasi belum bisa dikirim ke alamat ini. Hubungi pengelola aplikasi.',
            'over_email_send_rate_limit', 'over_request_rate_limit' => 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
            'signup_disabled', 'email_provider_disabled' => 'Pendaftaran akun sedang ditutup.',
            'user_banned' => 'Akun ini sedang diblokir.',
            default => 'Tidak bisa terhubung ke server. Coba lagi sebentar.',
        };
    }
}
