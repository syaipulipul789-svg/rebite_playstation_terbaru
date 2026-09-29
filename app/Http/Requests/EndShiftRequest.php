<?php

namespace App\Http\Requests;

use App\Services\ShiftService;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class EndShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCashier() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'actual_physical_cash' => $this->filled('actual_physical_cash')
                ? Money::parse($this->input('actual_physical_cash'))
                : null,
            'note' => $this->filled('note') ? trim((string) $this->input('note')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'actual_physical_cash' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_physical_cash.required' => 'Jumlah uang fisik di laci kasir wajib diisi.',
            'actual_physical_cash.numeric' => 'Jumlah uang fisik harus berupa angka.',
            'note.max' => 'Catatan keterangan selisih maksimal 1000 karakter.',
        ];
    }

    /**
     * Ekspektasi kas SELALU dihitung ulang dari shift aktif milik kasir di
     * server. Hidden field dari browser hanya dipakai untuk tampilan, tidak
     * pernah dipercaya untuk keputusan validasi.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(ShiftService::class);
            $shift = $service->activeShiftFor($this->user());

            if (! $shift) {
                throw ValidationException::withMessages([
                    'actual_physical_cash' => 'Tidak ada shift aktif. Mulai shift sebelum menutup shift.',
                ]);
            }

            $expected = $service->previewReconciliation($shift)['expected_cash'];
            $discrepancy = Money::round((float) $this->input('actual_physical_cash') - $expected);

            $this->merge(['expected_cash' => $expected, 'discrepancy' => $discrepancy]);

            if ($discrepancy !== 0.0 && blank($this->input('note'))) {
                $validator->errors()->add(
                    'note',
                    'Selisih kas tidak nol. Wajib menyertakan catatan keterangan selisih.'
                );
            }
        });
    }
}
