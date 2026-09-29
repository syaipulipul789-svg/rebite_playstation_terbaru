<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public const EVENT_SHIFT_STARTED = 'SHIFT_STARTED';

    public const EVENT_SHIFT_CLOSED = 'SHIFT_CLOSED';

    public const EVENT_SESSION_STARTED = 'SESSION_STARTED';

    public const EVENT_SESSION_EXTENDED = 'SESSION_EXTENDED';

    public const EVENT_SESSION_COMPLETED = 'SESSION_COMPLETED';

    public const EVENT_SESSION_CANCELLED = 'SESSION_CANCELLED';

    public const EVENT_MASTER_UPDATED = 'MASTER_UPDATED';

    protected $fillable = [
        'user_id',
        'shift_id',
        'event',
        'description',
        'context',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            self::EVENT_SHIFT_STARTED => 'Shift Dimulai',
            self::EVENT_SHIFT_CLOSED => 'Shift Ditutup',
            self::EVENT_SESSION_STARTED => 'Sewa Dimulai',
            self::EVENT_SESSION_EXTENDED => 'Durasi Ditambah',
            self::EVENT_SESSION_COMPLETED => 'Sewa Selesai',
            self::EVENT_SESSION_CANCELLED => 'Sewa Dibatalkan',
            self::EVENT_MASTER_UPDATED => 'Master Diubah',
            default => $this->event,
        };
    }

    public function eventBadgeClass(): string
    {
        return match ($this->event) {
            self::EVENT_SHIFT_CLOSED => 'bg-violet-500/15 text-violet-300 ring-1 ring-inset ring-violet-500/30',
            self::EVENT_SESSION_COMPLETED => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
            self::EVENT_SESSION_CANCELLED => 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30',
            self::EVENT_MASTER_UPDATED => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
            default => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
        };
    }
}
