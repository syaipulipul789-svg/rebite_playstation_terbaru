<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthenticationController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')->with('error', 'Login Google belum dikonfigurasi. Hubungi pengelola.');
        }

        $request->session()->forget(['google_link_user_id', 'google_registration']);

        return Socialite::driver('google')->redirect();
    }

    public function link(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return back()->with('error', 'Login Google belum dikonfigurasi. Hubungi pengelola.');
        }

        $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ]);

        if ($request->user()->google_id !== null) {
            return back()->with('error', 'Akun ini sudah terhubung ke Google.');
        }

        $request->session()->forget('google_registration');
        $request->session()->put('google_link_user_id', $request->user()->id);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->callbackFailure($request, 'Login Google dibatalkan. Silakan coba lagi.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->callbackFailure($request, 'Sesi Google tidak valid atau sudah kedaluwarsa. Silakan coba lagi.');
        }

        $googleId = (string) $googleUser->getId();
        $email = trim((string) $googleUser->getEmail());

        if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! ($googleUser->user['email_verified'] ?? false)) {
            return $this->callbackFailure($request, 'Akun Google harus memiliki email terverifikasi.');
        }

        $linkUserId = $request->session()->pull('google_link_user_id');

        if ($linkUserId !== null) {
            $currentUser = $request->user();

            if ($currentUser === null || $currentUser->id !== (int) $linkUserId || ! $currentUser->is_active) {
                return $this->callbackFailure($request, 'Sesi penghubungan akun berubah. Silakan masuk kembali.');
            }

            $linked = DB::transaction(function () use ($currentUser, $googleId, $email): bool {
                $user = User::query()->lockForUpdate()->findOrFail($currentUser->id);

                if ($user->google_id !== null || User::query()->where('google_id', $googleId)->exists()) {
                    return false;
                }

                if ($user->email !== null && strcasecmp($user->email, $email) !== 0) {
                    return false;
                }

                if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                    return false;
                }

                $user->forceFill([
                    'google_id' => $googleId,
                    'email' => $email,
                    'email_verified_at' => now(),
                ])->save();

                return true;
            });

            return redirect()->route('account.settings')->with($linked ? 'success' : 'error', $linked
                ? 'Akun Google berhasil dihubungkan. Sekarang Anda bisa masuk dengan Google.'
                : 'Akun Google sudah digunakan atau emailnya tidak sesuai. Akun tidak diubah.');
        }

        if ($request->user() !== null) {
            return redirect()->route('account.settings')->with('error', 'Untuk menghubungkan Google, gunakan tombol pada pengaturan akun.');
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user !== null) {
            if (! $user->is_active) {
                return redirect()->route('login')->with('error', 'Akun ini sedang tidak aktif. Hubungi pengelola.');
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget('google_registration');

            return redirect()->route($user->landingRoute());
        }

        if (User::query()->where('email', $email)->exists()) {
            return redirect()->route('login')->with('error', 'Email ini sudah terdaftar. Masuk dengan akun lama lalu hubungkan Google dari Pengaturan Akun.');
        }

        $request->session()->put('google_registration', [
            'id' => $googleId,
            'email' => $email,
            'name' => Str::limit(trim((string) $googleUser->getName()) ?: 'Pelanggan', 100, ''),
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
        ]);

        return redirect()->route('google.register');
    }

    public function registerForm(Request $request): View|RedirectResponse
    {
        $registration = $this->pendingRegistration($request);

        if ($registration === null) {
            return redirect()->route('login')->with('error', 'Sesi pendaftaran Google sudah kedaluwarsa. Silakan ulangi.');
        }

        return view('auth.google-register', ['googleAccount' => $registration]);
    }

    public function register(Request $request): RedirectResponse
    {
        $registration = $this->pendingRegistration($request);

        if ($registration === null) {
            return redirect()->route('login')->with('error', 'Sesi pendaftaran Google sudah kedaluwarsa. Silakan ulangi.');
        }

        $input = $request->validate(['phone' => ['required', 'string']]);
        $phone = Phone::normalize($input['phone']);
        $validated = validator(['phone' => $phone], [
            'phone' => ['required', 'regex:/^0[89]\d{7,11}$/', 'unique:users,phone', 'unique:users,username'],
        ], [
            'phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'phone.unique' => 'Nomor WhatsApp sudah terdaftar. Masuk dengan nomor tersebut lalu hubungkan Google dari Pengaturan Akun.',
        ])->validate();

        if (User::query()->where('email', $registration['email'])->orWhere('google_id', $registration['id'])->exists()) {
            return redirect()->route('login')->with('error', 'Akun sudah terdaftar. Masuk dengan akun lama lalu hubungkan Google.');
        }

        $user = DB::transaction(function () use ($registration, $validated): User {
            $user = new User([
                'name' => $registration['name'],
                'username' => $validated['phone'],
                'phone' => $validated['phone'],
                'email' => $registration['email'],
                'password' => Hash::make(Str::random(64)),
                'role' => UserRole::CUSTOMER,
                'is_active' => true,
            ]);
            $user->forceFill([
                'google_id' => $registration['id'],
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });

        $request->session()->forget('google_registration');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }

    /**
     * @return array{id: string, email: string, name: string, expires_at: int}|null
     */
    private function pendingRegistration(Request $request): ?array
    {
        $registration = $request->session()->get('google_registration');

        if (! is_array($registration) || ! isset($registration['id'], $registration['email'], $registration['name'], $registration['expires_at']) || $registration['expires_at'] <= now()->getTimestamp()) {
            $request->session()->forget('google_registration');

            return null;
        }

        return $registration;
    }

    private function callbackFailure(Request $request, string $message): RedirectResponse
    {
        $destination = $request->user() !== null ? 'account.settings' : 'login';
        $request->session()->forget(['google_link_user_id', 'google_registration']);

        return redirect()->route($destination)->with('error', $message);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
