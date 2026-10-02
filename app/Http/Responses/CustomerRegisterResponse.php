<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * Setelah pendaftaran, pelanggan langsung diarahkan ke dashboard booking-nya.
 *
 * Fallback bawaan Fortify (`config('fortify.home')`) masih benar untuk staff,
 * jadi kontrak ini hanya menutup jalur pendaftaran.
 */
class CustomerRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        // Saat user baru berhasil mendaftar, Fortify sudah meng-autentikasi
        // mereka, tapi kita ingin mengarahkan dengan jelas dan menambahkan
        // flash message supaya user tahu akun berhasil dibuat.
        return redirect()
            ->route('customer.dashboard')
            ->with('success', 'Akun berhasil dibuat. Selamat datang!');
    }
}
