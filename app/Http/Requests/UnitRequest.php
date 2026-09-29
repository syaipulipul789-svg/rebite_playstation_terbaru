<?php

namespace App\Http\Requests;

use App\Enums\UnitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')?->id;

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\- ]+$/', Rule::unique('units', 'code')->ignore($unitId)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'status' => ['required', Rule::in(array_column(UnitStatus::cases(), 'value'))],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode unit wajib diisi.',
            'code.unique' => 'Kode unit sudah dipakai unit lain.',
            'code.regex' => 'Kode unit hanya boleh huruf, angka, spasi, dan tanda hubung.',
            'name.required' => 'Nama unit wajib diisi.',
            'type.required' => 'Tipe/konsol wajib diisi.',
            'hourly_rate.min' => 'Tarif per jam tidak boleh negatif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode unit',
            'name' => 'nama unit',
            'type' => 'tipe konsol',
            'hourly_rate' => 'tarif per jam',
        ];
    }
}
