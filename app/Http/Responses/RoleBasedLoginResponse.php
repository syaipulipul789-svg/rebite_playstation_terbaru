<?php

namespace App\Http\Responses;

use App\Enums\ShiftStatus;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Pengalihan pasca-login:
 * - OWNER -> dashboard analitik
 * - KASIR -> grid unit bila sudah punya shift OPEN, selain itu gatekeeper shift.
 * - CUSTOMER -> dashboard booking (daftar unit kosong + booking sendiri).
 */
class RoleBasedLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            return redirect()->intended(route('customer.dashboard'));
        }

        if ($user->isOwner()) {
            return redirect()->intended(route('owner.dashboard'));
        }

        $hasOpenShift = $user->shifts()
            ->where('status', ShiftStatus::OPEN)
            ->exists();

        return redirect()->intended(
            $hasOpenShift ? route('units.index') : route('shift.start')
        );
    }
}
