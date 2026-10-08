<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    public function test_login_google_harus_dikonfigurasi_sebelum_digunakan(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);

        $this->get(route('google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_alur_google_memakai_state_session_dan_menolak_callback_dengan_state_salah(): void
    {
        config()->set('services.google.client_id', 'google-test-id');
        config()->set('services.google.client_secret', 'google-test-secret');
        config()->set('services.google.redirect', route('google.callback'));

        $this->get(route('google.redirect'))
            ->assertRedirectContains('https://accounts.google.com/o/oauth2/auth')
            ->assertSessionHas('state');

        $this->get(route('google.callback', ['state' => 'salah', 'code' => 'fake']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_akun_staf_dan_pelanggan_yang_terhubung_login_dengan_role_yang_sama(): void
    {
        $owner = $this->makeOwner();
        $cashier = $this->makeCashier();
        $customer = $this->makeCustomer();

        foreach ([$owner, $cashier, $customer] as $user) {
            $googleId = 'google-'.$user->id;
            $user->forceFill(['google_id' => $googleId])->save();
            $this->fakeGoogleUser($this->googleUser($googleId, $user->email ?? 'gamer@example.test'));

            $destination = match ($user->role) {
                UserRole::OWNER => 'owner.dashboard',
                UserRole::KASIR => 'shift.start',
                UserRole::CUSTOMER => 'customer.dashboard',
            };

            $this->get(route('google.callback'))
                ->assertRedirect(route($destination));

            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_akun_nonaktif_tidak_bisa_masuk_dengan_google(): void
    {
        $cashier = $this->makeCashier(attributes: ['is_active' => false]);
        $cashier->forceFill(['google_id' => 'google-nonaktif'])->save();
        $this->fakeGoogleUser($this->googleUser('google-nonaktif', $cashier->email));

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_email_staf_tidak_ditautkan_otomatis_ke_google_tanpa_login_biasa(): void
    {
        $owner = $this->makeOwner();
        $this->fakeGoogleUser($this->googleUser('google-as-a-guest', $owner->email));

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertNull($owner->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_baru_hanya_dapat_membuat_akun_pelanggan_dengan_nomor_whatsapp(): void
    {
        $this->fakeGoogleUser($this->googleUser('google-pelanggan-baru', 'baru@example.test'));

        $this->get(route('google.callback'))->assertRedirect(route('google.register'));
        $this->get(route('google.register'))->assertOk()->assertSee('baru@example.test');

        $this->post(route('google.register.store'), ['phone' => '+62 812-3456-7890'])
            ->assertRedirect(route('customer.dashboard'));

        $user = User::query()->where('google_id', 'google-pelanggan-baru')->firstOrFail();
        $this->assertSame(UserRole::CUSTOMER, $user->role);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame('081234567890', $user->username);
        $this->assertSame('baru@example.test', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_nomor_pelanggan_lama_tidak_boleh_dipakai_untuk_mendaftarkan_google_baru(): void
    {
        $customer = $this->makeCustomer();
        $this->fakeGoogleUser($this->googleUser('google-baru', 'baru@example.test'));

        $this->get(route('google.callback'))->assertRedirect(route('google.register'));
        $this->post(route('google.register.store'), ['phone' => $customer->phone])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_kasir_bisa_menghubungkan_google_setelah_masukkan_password_lalu_login_dengan_google(): void
    {
        config()->set('services.google.client_id', 'google-test-id');
        config()->set('services.google.client_secret', 'google-test-secret');
        $cashier = $this->makeCashier();

        $this->actingAs($cashier)->post(route('google.link'), [
            'current_password' => 'keliru',
        ])->assertSessionHasErrors('current_password');
        $this->assertNull($cashier->fresh()->google_id);

        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->actingAs($cashier)->post(route('google.link'), [
            'current_password' => 'password',
        ])->assertRedirect('https://accounts.google.com/o/oauth2/auth');

        $this->fakeGoogleUser($this->googleUser('google-kasir', $cashier->email));
        $this->actingAs($cashier)->get(route('google.callback'))
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('success');

        $this->assertSame('google-kasir', $cashier->fresh()->google_id);
        $this->assertSame(UserRole::KASIR, $cashier->fresh()->role);
    }

    public function test_akun_staf_tidak_bisa_menghubungkan_google_dengan_email_yang_berbeda(): void
    {
        $owner = $this->makeOwner();
        $this->fakeGoogleUser($this->googleUser('google-salah-email', 'oranglain@example.test'));

        $this->actingAs($owner)->withSession(['google_link_user_id' => $owner->id])
            ->get(route('google.callback'))
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('error');

        $this->assertNull($owner->fresh()->google_id);
        $this->assertSame('owner@rebite.test', $owner->fresh()->email);
    }

    public function test_pelanggan_lama_bisa_menghubungkan_google_tanpa_membuat_akun_kedua(): void
    {
        $customer = $this->makeCustomer();
        $this->fakeGoogleUser($this->googleUser('google-pelanggan-lama', 'lama@example.test'));

        $this->actingAs($customer)->withSession(['google_link_user_id' => $customer->id])
            ->get(route('google.callback'))
            ->assertRedirect(route('account.settings'));

        $this->assertSame('google-pelanggan-lama', $customer->fresh()->google_id);
        $this->assertSame('lama@example.test', $customer->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_yang_sudah_terhubung_ke_akun_lain_tidak_bisa_diambil(): void
    {
        $first = $this->makeCustomer();
        $second = $this->makeCustomer('089999999999');
        $first->forceFill(['google_id' => 'google-sudah-ada'])->save();
        $this->fakeGoogleUser($this->googleUser('google-sudah-ada', 'baru@example.test'));

        $this->actingAs($second)->withSession(['google_link_user_id' => $second->id])
            ->get(route('google.callback'))
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('error');

        $this->assertNull($second->fresh()->google_id);
        $this->assertSame('google-sudah-ada', $first->fresh()->google_id);
    }

    public function test_callback_tolak_sesi_akun_yang_berubah_saat_menghubungkan_google(): void
    {
        $owner = $this->makeOwner();
        $cashier = $this->makeCashier();
        $this->fakeGoogleUser($this->googleUser('google-berubah', $owner->email));

        $this->actingAs($cashier)->withSession(['google_link_user_id' => $owner->id])
            ->get(route('google.callback'))
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('error');

        $this->assertNull($owner->fresh()->google_id);
        $this->assertNull($cashier->fresh()->google_id);
    }

    public function test_callback_google_tidak_valid_dan_email_tidak_diverifikasi_ditolak(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->fakeGoogleUser($this->googleUser('google-unverified', 'baru@example.test', false));
        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_pendaftaran_google_kedaluwarsa_tidak_bisa_dilanjutkan(): void
    {
        $this->withSession(['google_registration' => [
            'id' => 'expired',
            'email' => 'expired@example.test',
            'name' => 'Pelanggan',
            'expires_at' => now()->subMinute()->getTimestamp(),
        ]])->post(route('google.register.store'), ['phone' => '081234567890'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('users', 0);
    }

    private function googleUser(string $id, string $email, bool $verified = true): GoogleUser
    {
        return (new GoogleUser)->setRaw([
            'sub' => $id,
            'email' => $email,
            'email_verified' => $verified,
            'name' => 'Google Gamer',
        ])->map([
            'id' => $id,
            'email' => $email,
            'name' => 'Google Gamer',
        ]);
    }

    private function fakeGoogleUser(GoogleUser $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
