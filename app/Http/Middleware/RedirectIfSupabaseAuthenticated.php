<?php

namespace App\Http\Middleware;

use App\Support\SupabaseSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman masuk/daftar tidak perlu dibuka lagi oleh user yang sudah login.
 */
class RedirectIfSupabaseAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SupabaseSession::check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
