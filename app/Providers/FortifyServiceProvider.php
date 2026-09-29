<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\RoleBasedLoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Fortify 1.40 tidak lagi mem-bundle view, jadi view daftarkan manual.
        Fortify::loginView('auth.login');
        Fortify::requestPasswordResetLinkView('auth.forgot-password');
        Fortify::resetPasswordView('auth.reset-password');

        // Autentikasi memakai USERNAME (bukan email) untuk kasir di meja kasir.
        Fortify::username('username');

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::findForLogin(trim((string) $request->input(Fortify::username())));

            if ($user === null || ! $user->is_active) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['Username atau password salah.'],
                ]);
            }

            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['Username atau password salah.'],
                ]);
            }

            return $user;
        });

        // Pengalihan setelah login berdasarkan role.
        $this->app->singleton(LoginResponse::class, fn () => new RoleBasedLoginResponse);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username()))).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
