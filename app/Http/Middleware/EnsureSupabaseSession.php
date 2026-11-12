<?php

namespace App\Http\Middleware;

use App\Exceptions\SupabaseException;
use App\Services\SupabaseService;
use App\Support\SupabaseSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman ini hanya untuk user yang sudah login lewat Supabase Auth.
 * Access token yang hampir habis diperbarui di sini memakai refresh token.
 */
class EnsureSupabaseSession
{
    /** Perbarui token jika sisa umurnya kurang dari ini (detik). */
    private const REFRESH_MARGIN_SECONDS = 60;

    public function __construct(private SupabaseService $supabase) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! SupabaseSession::check()) {
            return redirect()->guest(route('login'));
        }

        if (SupabaseSession::expiresWithin(self::REFRESH_MARGIN_SECONDS)) {
            try {
                SupabaseSession::store($this->supabase->refreshSession((string) SupabaseSession::refreshToken()));
            } catch (SupabaseException) {
                SupabaseSession::clear();

                return redirect()->guest(route('login'))
                    ->with('status', 'Sesi kamu sudah berakhir. Silakan masuk lagi.');
            }
        }

        View::share('authUser', SupabaseSession::user());

        return $next($request);
    }
}
