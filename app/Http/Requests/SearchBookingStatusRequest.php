<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'booking_code' => ['required', 'string', 'regex:/^BK-?\d{1,10}$/i'],
            'customer_phone' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'booking_code.required' => 'Kode booking wajib diisi.',
            'booking_code.regex' => 'Format kode booking tidak valid. Contoh: BK-0004.',
            'customer_phone.required' => 'Nomor WhatsApp wajib diisi.',
            'customer_phone.max' => 'Nomor WhatsApp terlalu panjang.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'booking_code' => 'kode booking',
            'customer_phone' => 'nomor WhatsApp',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'booking_code' => strtoupper(trim((string) $this->input('booking_code'))),
        ]);
    }

    /**
     * Primary key dari kode booking, mis. "BK-0004" / "bk4" -> 4.
     */
    public function bookingId(): ?int
    {
        if (preg_match('/^BK-?0*(\d+)$/', (string) $this->input('booking_code'), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
