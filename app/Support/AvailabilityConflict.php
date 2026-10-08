<?php

namespace App\Support;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Dilempar ketika sebuah unit tidak tersedia untuk slot jam yang diminta,
 * karena bentrok dengan sesi rental berjalan, booking terkonfirmasi, atau
 * permintaan sewa lain yang masih mengunci slot.
 */
final class AvailabilityConflict extends \RuntimeException
{
    public static function forUnit(Unit $unit, Collection $conflicts): self
    {
        return new self(
            sprintf('Unit "%s" (%s) tidak tersedia pada jadwal tersebut. %s', $unit->name, $unit->code, $conflicts->implode(' ')),
        );
    }
}
