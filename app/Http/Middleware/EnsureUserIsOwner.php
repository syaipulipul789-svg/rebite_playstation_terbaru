<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses ke halaman khusus OWNER (dashboard analitik, laporan keuangan,
 * audit log, dan CRUD data master).
 */
class EnsureUserIsOwner
{
    /**
     * Satu-satunya definisi "boleh lewat" untuk OWNER.
     *
     * Dipakai juga oleh `RoleBasedLoginResponse` saat memeriksa apakah halaman
     * yang ingin dituju setelah login boleh dibuka akun ini, supaya penilaian
     * role tidak pernah dobel dan tidak bisa berbeda antara middleware dan
     * login.
     */
    public static function allows(?User $user): bool
    {
        return $user?->isOwner() ?? false;
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

        abort(Response::HTTP_FORBIDDEN, 'Halaman ini hanya untuk Owner.');
    }
}
