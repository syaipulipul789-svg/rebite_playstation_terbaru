<?php

namespace App\Http\Middleware;

use App\Enums\ShiftStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeper shift untuk role KASIR.
 *
 * Kasir tidak boleh menyentuh halaman Grid Unit / POS / Rekonsiliasi sebelum
 * menginput Modal Awal Kas. Owner dilewati karena tidak memakai laci kasir.
 * Request JSON/API dibalas 423 (Locked) agar Alpine bisa menampilkan toast,
 * bukan redirect.
 */
class CheckActiveShift
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isOwner()) {
            return $next($request);
        }

        $activeShift = $user->shifts()
            ->where('status', ShiftStatus::OPEN)
            ->latest('start_time')
            ->first();

        if ($activeShift !== null) {
            // Bagikan shift aktif ke seluruh view lewat request attribute.
            $request->attributes->set('activeShift', $activeShift);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Shift belum dimulai. Input modal awal kas terlebih dahulu.',
                'redirect' => route('shift.start'),
            ], Response::HTTP_LOCKED);
        }

        return redirect()
            ->route('shift.start')
            ->with('warning', 'Mulai shift dengan menginput modal awal kas sebelum membuka halaman ini.');
    }
}
