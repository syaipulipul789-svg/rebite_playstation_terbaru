<?php

namespace App\Http\Responses;

use App\Http\Middleware\EnsureUserIsCashier;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsOwner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Pengalihan pasca-login.
 *
 * Halaman tujuan ditentukan oleh `User::landingRoute()` — satu sumber
 * kebenaran yang juga dipakai route `dashboard` dan landing page `/`.
 *
 * `url.intended` tetap dihormati (agar deep-link tidak hilang), TETAPI hanya
 * kalau halaman itu memang boleh dibuka role yang sedang login. Tanpa
 * pemeriksaan ini, pelanggan yang membuka `/pos` lalu login akan dilempar ke
 * halaman kasir dan hanya melihat 403 — atau, lebih buruk, kasir diarahkan ke
 * area pelanggan.
 */
class RoleBasedLoginResponse implements LoginResponseContract
{
    /**
     * Middleware role → cara memeriksa, memakai definisi yang sama dengan
     * middleware aslinya supaya tidak mungkin berbeda.
     *
     * @var array<string, callable(?User): bool>
     */
    private const ROLE_GUARDS = [
        'role.owner' => [EnsureUserIsOwner::class, 'allows'],
        'role.cashier' => [EnsureUserIsCashier::class, 'allows'],
        'role.customer' => [EnsureUserIsCustomer::class, 'allows'],
    ];

    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        $landing = route($user->landingRoute());

        $intended = $this->accessibleIntendedUrl($request, $user);

        return redirect()->to($intended ?? $landing);
    }

    /**
     * URL `url.intended` bila route-nya ada DAN role user boleh membukanya.
     */
    private function accessibleIntendedUrl(Request $request, User $user): ?string
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(
                Request::create($intended, 'GET')
            );
        } catch (NotFoundHttpException|Throwable) {
            // URL yang sudah tidak ada route-nya (mis. menu lama) — pakai landing.
            return null;
        }

        foreach ($this->roleGuardsFor($route) as $guard) {
            if (! $guard($user)) {
                return null;
            }
        }

        return $intended;
    }

    /**
     * Semua pemeriksaan role yang menempel pada route tersebut.
     *
     * @return list<callable(?User): bool>
     */
    private function roleGuardsFor(RoutingRoute $route): array
    {
        $guards = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! isset(self::ROLE_GUARDS[$middleware])) {
                continue;
            }

            [$class, $method] = self::ROLE_GUARDS[$middleware];

            $guards[] = fn (?User $user) => $class::$method($user);
        }

        return $guards;
    }
}
