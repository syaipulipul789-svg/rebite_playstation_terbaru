<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kirim pesanan dari halaman pemesanan mandiri /m/{unit_code}. Publik
 * (tanpa login): otorisasinya dicegah oleh controller yang hanya membuka
 * form kalau unit punya sesi sewa aktif, dan stoknya dikunci ulang di
 * service saat transaksi dibuat.
 *
 * Keranjang dikirim sebagai array baris (bukan satu baris per produk) karena
 * produk yang sama bisa muncul lebih dari sekali dengan catatan berbeda,
 * mis. "Nasi Goreng pedas" dan "Nasi Goreng tidak pedas".
 */
class StoreTableOrderRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:120'],
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
            'notes.max' => 'Catatan terlalu panjang (maksimal 500 karakter).',
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
            'items.max' => 'Maksimal 50 baris pesanan sekaligus.',
            'items.*.product_id.required' => 'Ada item tanpa produk yang valid.',
            'items.*.product_id.exists' => 'Salah satu produk sudah tidak tersedia.',
            'items.*.qty.min' => 'Jumlah minimal 1 per item.',
            'items.*.qty.max' => 'Jumlah maksimal 50 per item.',
            'items.*.notes.max' => 'Catatan item terlalu panjang (maksimal 120 karakter).',
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
            'notes' => 'catatan',
            'items' => 'keranjang',
        ];
    }

    /**
     * Paksa qty jadi integer supaya "2" dari input teks tidak lolos validasi
     * `integer` yang lurus dan gagal diam-diam.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $this->merge([
            'items' => array_map(
                fn ($line) => is_array($line)
                    ? $line + ['qty' => (int) ($line['qty'] ?? 0)]
                    : $line,
                $items,
            ),
        ]);
    }
}
