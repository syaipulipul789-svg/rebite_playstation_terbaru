<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Middleware\EnsureShiftClosedBeforeLogout;
use App\Http\Responses\CustomerRegisterResponse;
use App\Http\Responses\LogoutToLoginResponse;
use App\Http\Responses\RoleBasedLoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
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
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        // Pendaftaran memakai aksi milik aplikasi sendiri supaya bisa
        // memvalidasi nomor WhatsApp dan membuat akun dengan role CUSTOMER.
        Fortify::createUsersUsing(CreateNewUser::class);

        // Fortify 1.40 tidak lagi mem-bundle view, jadi view daftarkan manual.
        Fortify::loginView('auth.login');
        Fortify::registerView('auth.register');
        Fortify::requestPasswordResetLinkView('auth.forgot-password');
        Fortify::resetPasswordView('auth.reset-password');

        // Autentikasi memakai USERNAME (bukan email) untuk kasir di meja kasir.
        // Pelanggan memakai kolom yang sama, tapi isinya nomor WhatsApp-nya.
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

        // Setelah logout everyone mendarat di halaman masuk, bukan di landing
        // page pelanggan seperti fallback bawaan Fortify.
        $this->app->singleton(LogoutResponse::class, fn () => new LogoutToLoginResponse);

        // Pelanggan yang baru daftar langsung masuk ke dashboard booking-nya,
        // bukan ke fallback `/dashboard` milik staff.
        $this->app->singleton(RegisterResponse::class, fn () => new CustomerRegisterResponse);

        $this->guardLogoutWithOpenShift();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username()))).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Pendaftaran dibuka untuk umum, jadi limiter-nya lebih longgar dari
        // login: satu IP masih boleh membuat beberapa akun berbeda.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)->by((string) $request->ip());
        });
    }

    /**
     * Tempelkan penjaga shift ke route `logout` milik Fortify.
     *
     * Fortify mendaftarkan route login/logout sendiri dan tidak menyediakan
     * hook middleware, jadi route-nya yang ditambahi middleware — jauh lebih
     * sempit dibanding meng-globalkan ke seluruh group `web`.
     */
    private function guardLogoutWithOpenShift(): void
    {
        $this->app->booted(function (): void {
            $routes = $this->app['router']->getRoutes();
            $logout = $routes->getByName('logout');

            if ($logout instanceof Route) {
                $logout->middleware(EnsureShiftClosedBeforeLogout::class);
            }
        });
    }
}
