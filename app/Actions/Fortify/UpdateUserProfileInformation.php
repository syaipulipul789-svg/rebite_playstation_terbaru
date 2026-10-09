<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('users')->ignore($user->id),
                // Nomor yang sama bisa ditulis "+62 812..." atau "0812...";
                // bandingkan dalam semua varian supaya unique benar-benar aman.
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    $variants = Phone::variants(is_string($value) ? $value : null);

                    if ($variants === []) {
                        return;
                    }

                    $taken = User::query()
                        ->whereKeyNot($user->id)
                        ->whereIn('phone', $variants)
                        ->exists();

                    if ($taken) {
                        $fail('Nomor WhatsApp tersebut sudah terdaftar.');
                    }
                },
            ],

            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        $user->forceFill([
            'name' => $input['name'],
            'username' => $input['username'],
            'phone' => Phone::normalize($input['phone'] ?? null) ?: null,
            'email' => filled($input['email'] ?? null) ? $input['email'] : null,
        ])->save();
    }
}
