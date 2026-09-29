<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddRentalTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'extra_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'extra_minutes.required' => 'Tentukan durasi tambahan.',
            'extra_minutes.min' => 'Durasi tambahan minimal 5 menit.',
            'extra_minutes.max' => 'Durasi tambahan maksimal 1440 menit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['extra_minutes' => (int) $this->input('extra_minutes')]);
    }
}
