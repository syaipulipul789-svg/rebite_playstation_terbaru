<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'rate_package_id' => ['nullable', 'integer', Rule::exists('rate_packages', 'id')],
            'is_free_play' => ['required', 'boolean'],
            'open_play_minutes' => ['nullable', 'required_if:is_free_play,1', 'integer', 'min:5', 'max:1440'],
            'payment_method' => ['nullable', Rule::in(['CASH', 'QRIS'])],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.required' => 'Unit wajib dipilih.',
            'rate_package_id.exists' => 'Paket jam tidak valid.',
            'open_play_minutes.required_if' => 'Tentukan durasi open play (menit).',
            'open_play_minutes.min' => 'Durasi open play minimal 5 menit.',
        ];
    }

    public function attributes(): array
    {
        return [
            'unit_id' => 'unit',
            'rate_package_id' => 'paket jam',
            'open_play_minutes' => 'durasi open play',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_free_play' => $this->boolean('is_free_play'),
            'open_play_minutes' => $this->input('open_play_minutes') !== null
                ? (int) $this->input('open_play_minutes')
                : null,
        ]);
    }
}
