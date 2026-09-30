<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Membuka pesanan baru dari halaman scan pelanggan. Publik (tanpa login),
 * jadi otorisasinya selalu true — identitas pelanggan hanya berupa nama
 * + nomor WhatsApp yang dipakai kasir saat-money pembayaran.
 */
class StartCustomerOrderRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'booking_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama wajib diisi.',
            'customer_name.max' => 'Nama terlalu panjang.',
            'customer_phone.required' => 'Nomor WhatsApp wajib diisi.',
            'unit_id.exists' => 'Unit yang dipilih tidak ditemukan.',
            'booking_code.max' => 'Kode booking terlalu panjang.',
            'notes.max' => 'Catatan terlalu panjang (maksimal 500 karakter).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'nama',
            'customer_phone' => 'nomor WhatsApp',
            'unit_id' => 'unit',
            'booking_code' => 'kode booking',
            'notes' => 'catatan',
        ];
    }
}
