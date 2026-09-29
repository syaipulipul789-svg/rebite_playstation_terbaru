<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses ke halaman khusus OWNER (dashboard analitik, laporan keuangan,
 * audit log, dan CRUD data master).
 */
class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED)
                : redirect()->route('login');
        }

        if ($user->isOwner()) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'Halaman ini hanya untuk Owner.');
    }
}
