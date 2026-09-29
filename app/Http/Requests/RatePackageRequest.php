<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'unit_type' => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama paket wajib diisi.',
            'duration_minutes.required' => 'Durasi paket wajib diisi.',
            'duration_minutes.min' => 'Durasi minimal 5 menit.',
            'price.required' => 'Harga paket wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama paket',
            'unit_type' => 'tipe unit',
            'duration_minutes' => 'durasi',
            'price' => 'harga',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
