<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses ke halaman operasional KASIR (grid unit, POS, input modal awal
 * kas, dan API Alpine). Owner yang sedang membuka halaman ini tetap diizinkan
 * agar bisa memantau unit secara read-only.
 *
 * Ini middleware yang membedakan akun staff dari akun pelanggan. Semua route
 * di dalam `routes/web.php` yang berada di area kasir wajib memakainya —
 * `CheckActiveShift` hanya mengatur soal shift, bukan soal role.
 */
class EnsureUserIsCashier
{
    /**
     * Satu-satunya definisi "boleh lewat" untuk area kasir.
     *
     * `RoleBasedLoginResponse` memakai definisi yang sama supaya kasir yang
     * login tidak pernah diarahkan ke halaman pelanggan, dan sebaliknya.
     */
    public static function allows(?User $user): bool
    {
        return $user?->isStaff() ?? false;
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

        abort(Response::HTTP_FORBIDDEN, 'Halaman ini hanya untuk kasir.');
    }
}
