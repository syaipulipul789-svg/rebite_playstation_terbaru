<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Setelah logout, semua orang diarahkan ke halaman masuk.
 *
 * Fallback bawaan Fortify mengirim ke `/`, yaitu landing page pelanggan. Itu
 * membingungkan untuk kasir: mereka baru saja menutup laci kasir lalu mendarat
 * di halaman promosi. Ke halaman login lebih jelas, dan konfirmasi keluar tetap
 * tampil di sana lewat flash message.
 */
class LogoutToLoginResponse implements LogoutResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Berhasil keluar. Terima kasih!');
    }
}
