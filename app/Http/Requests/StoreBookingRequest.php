<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::CUSTOMER;
    }

    /**
     * Nama dan nomor WhatsApp sengaja tidak divalidasi dari sini: keduanya
     * diambil dari akun pelanggan yang sedang login, jadi tidak bisa diisi
     * atau ditimpa lewat request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'console_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'start_time' => ['required', 'date', 'after:now', 'before:now +14 days'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'console_id.required' => 'Unit wajib dipilih.',
            'console_id.exists' => 'Unit yang dipilih tidak ditemukan.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date' => 'Format jam mulai tidak valid.',
            'start_time.after' => 'Jam mulai harus di masa depan.',
            'start_time.before' => 'Jam mulai maksimal 14 hari ke depan.',
            'duration_hours.required' => 'Durasi wajib diisi.',
            'duration_hours.integer' => 'Durasi harus berupa angka jam.',
            'duration_hours.min' => 'Durasi minimal 1 jam.',
            'duration_hours.max' => 'Durasi maksimal 12 jam.',
            'notes.max' => 'Catatan terlalu panjang (maksimal 500 karakter).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'console_id' => 'unit',
            'start_time' => 'jam mulai',
            'duration_hours' => 'durasi',
            'notes' => 'catatan',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'duration_hours' => $this->input('duration_hours') !== null
                ? (int) $this->input('duration_hours')
                : null,
        ]);
    }
}
