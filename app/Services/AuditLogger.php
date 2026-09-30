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
     *
     * `$user` / `$shift` boleh null untuk aksi yang tidak dilakukan staf —
     * pesanan yang dikirim pelanggan dari HP, misalnya.
     */
    public function record(
        string $event,
        string $description,
        array $context = [],
        ?User $user = null,
        ?Shift $shift = null,
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
