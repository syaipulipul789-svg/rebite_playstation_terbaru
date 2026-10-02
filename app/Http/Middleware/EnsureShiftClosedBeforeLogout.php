<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cegah kasir logout selagi shift-nya masih OPEN.
 *
 * Kalau dibiarkan, shift menggantung di status OPEN tanpa ada yang
 * merekonsiliasi: uang kas di laci tidak pernah dihitung, dan karena
 * `ShiftService::activeShiftFor()` mencari shift milik user itu sendiri,
 * shift terlantar itu tidak akan terlihat oleh kasir manapun yang login
 * berikutnya. Apply guard ini di route `logout` milik Fortify.
 */
class EnsureShiftClosedBeforeLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Pelanggan tidak punya laci kas, jadi logout mereka bebas.
        if ($user === null || ! $user->isStaff()) {
            return $next($request);
        }

        $activeShift = $user->activeShift();

        if ($activeShift === null) {
            return $next($request);
        }

        // Request JSON (mis. tombol keluar di SPA) tidak bisa menampilkan
        // halaman rekonsiliasi, jadi balas 423 agar Alpine bisa memberi toast.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Shift masih berjalan. Tutup shift dan hitung uang fisik sebelum keluar.',
                'redirect' => route('shift.end'),
            ], Response::HTTP_LOCKED);
        }

        return redirect()
            ->route('shift.end')
            ->with('warning', 'Shift masih berjalan. Rekonsiliasi uang kas dulu sebelum keluar.');
    }
}
