<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CreateRentalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time', function (string $attribute, mixed $value, \Closure $fail) {
                $start = Carbon::parse($this->input('start_time'));
                $end = Carbon::parse($value);

                if ($start->diffInMinutes($end) < 60) {
                    $fail('Durasi sewa minimal 1 jam.');
                }
            }],
            'package_id' => ['nullable', 'integer', 'exists:rate_packages,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function customer(): array
    {
        $user = $this->user();

        return [
            'name' => $user?->name,
            'phone' => $user?->phone ? Phone::normalize($user->phone) : null,
            'whatsapp' => $user?->whatsapp ? Phone::normalize($user->whatsapp) : null,
            'user_id' => $user?->id,
        ];
    }
}
