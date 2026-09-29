<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit_type',
        'duration_minutes',
        'price',
        'is_active',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUnitType(Builder $query, ?string $type): Builder
    {
        return $query->where(function (Builder $q) use ($type) {
            $q->whereNull('unit_type');

            if ($type) {
                $q->orWhere('unit_type', $type);
            }
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('duration_minutes');
    }

    public function durationLabel(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours === 0) {
            return "{$minutes} Menit";
        }

        return $minutes === 0 ? "{$hours} Jam" : "{$hours} Jam {$minutes} Menit";
    }
}
