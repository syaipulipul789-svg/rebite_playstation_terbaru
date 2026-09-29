<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses ke halaman operasional KASIR. Owner yang sedang membuka
 * halaman ini tetap diizinkan agar bisa memantau unit secara read-only.
 */
class EnsureUserIsCashier
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED)
                : redirect()->route('login');
        }

        if ($user->isOwner() || $user->isCashier()) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
