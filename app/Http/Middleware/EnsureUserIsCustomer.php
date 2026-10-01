<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses ke area pelanggan: daftar unit yang kosong, form booking, dan
 * riwayat booking milik akun yang sedang login.
 *
 * Kasir dan owner memakai area internal masing-masing, jadi mereka yang salah
 * membuka URL pelanggan diberi 403 — bukan diam-diam diarahkan.
 */
class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED)
                : redirect()->route('login');
        }

        if ($user->isCustomer()) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'Halaman ini hanya untuk akun pelanggan.');
    }
}
