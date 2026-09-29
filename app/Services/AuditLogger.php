<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;

final class AuditLogger
{
    /**
     * Tulis jejak audit. Sengaja di-swallow agar kegagalan audit tidak
     * membatalkan transaksi bisnis (mis. tutup shift / selesai sewa).
     */
    public function record(
        ?User $user,
        ?Shift $shift,
        string $event,
        string $description,
        array $context = [],
    ): void {
        try {
            AuditLog::create([
                'user_id' => $user?->id,
                'shift_id' => $shift?->id,
                'event' => $event,
                'description' => $description,
                'context' => $context === [] ? null : $context,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
