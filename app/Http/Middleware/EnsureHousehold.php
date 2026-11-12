<?php

namespace App\Http\Middleware;

use App\Services\HouseholdService;
use App\Support\SupabaseSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman aplikasi butuh rumah aktif. User yang belum punya rumah diarahkan ke /rumah/mulai.
 * Dipasang setelah EnsureSupabaseSession.
 */
class EnsureHousehold
{
    public function __construct(private HouseholdService $households) {}

    public function handle(Request $request, Closure $next): Response
    {
        $household = SupabaseSession::household();

        if ($household === null) {
            $user = SupabaseSession::user();
            $household = $this->households->firstFor((string) SupabaseSession::accessToken(), (string) ($user['id'] ?? ''));

            if ($household === null) {
                return redirect()->route('household.setup');
            }

            SupabaseSession::setHousehold($household);
        }

        View::share('authHousehold', $household);

        return $next($request);
    }
}
