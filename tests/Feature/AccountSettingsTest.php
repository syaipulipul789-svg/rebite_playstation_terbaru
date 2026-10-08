<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    public function test_kasir_dan_owner_bisa_mengganti_password_sendiri_berulang_kali(): void
    {
        foreach ([$this->makeCashier(), $this->makeOwner()] as $user) {
            $this->actingAs($user)->get(route('account.settings'))
                ->assertOk()
                ->assertSee('Ubah Password');

            $this->actingAs($user)->from(route('account.settings'))
                ->put(route('user-password.update'), [
                    'current_password' => 'password',
                    'password' => 'BaruPertama123',
                    'password_confirmation' => 'BaruPertama123',
                ])->assertRedirect(route('account.settings'));

            $this->assertTrue(Hash::check('BaruPertama123', $user->fresh()->password));

            $this->actingAs($user)->from(route('account.settings'))
                ->put(route('user-password.update'), [
                    'current_password' => 'BaruPertama123',
                    'password' => 'BaruKedua456',
                    'password_confirmation' => 'BaruKedua456',
                ])->assertRedirect(route('account.settings'));

            $this->assertTrue(Hash::check('BaruKedua456', $user->fresh()->password));
            $this->assertFalse(Hash::check('password', $user->fresh()->password));
        }
    }

    public function test_password_salah_atau_tanpa_konfirmasi_tidak_mengubah_password(): void
    {
        $cashier = $this->makeCashier();

        $this->actingAs($cashier)->put(route('user-password.update'), [
            'current_password' => 'keliru',
            'password' => 'BaruPertama123',
            'password_confirmation' => 'BaruPertama123',
        ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->actingAs($cashier)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'BaruPertama123',
            'password_confirmation' => 'Berbeda456',
        ])->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('password', $cashier->fresh()->password));
    }

    public function test_tamu_tidak_bisa_membuka_pengaturan_atau_mengganti_password(): void
    {
        $this->get(route('account.settings'))->assertRedirect(route('login'));
        $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'BaruPertama123',
            'password_confirmation' => 'BaruPertama123',
        ])->assertRedirect(route('login'));
    }

    public function test_pelanggan_tidak_bisa_membuka_halaman_operasional_staf(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->get(route('account.settings'))->assertOk();
        $this->actingAs($customer)->get(route('units.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('shift.start'))->assertForbidden();
        $this->actingAs($customer)->post(route('shift.start.store'), ['starting_cash' => 100000])->assertForbidden();
    }
}
