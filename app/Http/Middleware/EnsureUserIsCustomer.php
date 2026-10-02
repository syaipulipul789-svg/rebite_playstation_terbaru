<?php

namespace App\Http\Middleware;

use App\Models\User;
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
    /**
     * Satu-satunya definisi "boleh lewat" untuk area pelanggan.
     */
    public static function allows(?User $user): bool
    {
        return $user?->isCustomer() ?? false;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED)
                : redirect()->guest(route('login'));
        }

        if (self::allows($user)) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'Halaman ini hanya untuk akun pelanggan.');
    }
}
