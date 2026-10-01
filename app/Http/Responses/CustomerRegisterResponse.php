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
        return redirect()->route('customer.dashboard');
    }
}
