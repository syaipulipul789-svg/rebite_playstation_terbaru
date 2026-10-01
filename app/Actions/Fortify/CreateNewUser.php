<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Daftarkan akun pelanggan baru.
     *
     * Identitasnya nomor WhatsApp, bukan email: pelanggan cuma perlu Remember
     * satu nomor untuk masuk dari perangkat mana pun, dan nomor itu sudah
     * dibutuhkan kasir untuk mengonfirmasi booking. Email sengaja dikosongkan
     * supaya tidak ada akun yang menumpuk pada email placeholder.
     *
     * Kolom `username` diisi dengan nomor yang sudah dinormalkan karena Fortify
     * memakai kolom itu sebagai kunci login — begini satu akun cukup untuk staff
     * (username asli) maupun pelanggan (nomor HP).
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // Normalkan dulu supaya validasi (dan unique) selalu berjalan atas
        // bentuk lokal, bukan atas whatever yang diketik pengguna. Kalau
        // validasi memeriksa input mentah, "+62 812-3456-7890" akan gagal
        // pola meski nomor tersebut sah.
        $phone = Phone::normalize($input['phone'] ?? '');

        Validator::make(
            [...$input, 'phone' => $phone],
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => [
                    'required',
                    'regex:/^0[89]\d{7,11}$/',
                    'unique:users,phone',
                ],
                'password' => $this->passwordRules(),
            ],
            [
                'name.required' => 'Nama wajib diisi.',
                'name.max' => 'Nama terlalu panjang.',
                'phone.required' => 'Nomor WhatsApp wajib diisi.',
                'phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
                'phone.unique' => 'Nomor WhatsApp ini sudah terdaftar. Silakan masuk.',
            ],
        )->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $phone,
            'phone' => $phone,
            'email' => null,
            'password' => Hash::make($input['password']),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);
    }
}
