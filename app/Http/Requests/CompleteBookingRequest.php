<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ];
    }

    /**
     * Method pembayaran hanya bermakna untuk booking yang sudah jadi sesi
     * rental berjalan; booking masa depan cukup ditandai selesai saja.
     */
    public function paymentMethod(): ?PaymentMethod
    {
        $value = $this->validated('payment_method');

        return $value !== null ? PaymentMethod::from($value) : null;
    }
}
