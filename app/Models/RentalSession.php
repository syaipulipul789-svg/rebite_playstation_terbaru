<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'shift_id',
        'user_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'planned_minutes',
        'is_free_play',
        'package_name',
        'rental_fee',
        'status',
        'payment_method',
        'extra_minutes',
        'extra_charges_total',
        'time_up_notified_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'time_up_notified_at' => 'datetime',
            'is_free_play' => 'boolean',
            'duration_minutes' => 'integer',
            'planned_minutes' => 'integer',
            'extra_minutes' => 'integer',
            'rental_fee' => 'decimal:2',
            'extra_charges_total' => 'integer',
            'status' => RentalSessionStatus::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class);
    }

    /**
     * Pesanan F&B yang dibuat dari QR meja selama sesi ini berjalan.
     *
     * Dipisah dari `items()` (pesanan yang kasir input manual dari grid)
     * supaya billable bisa dibedakan dan dirinci terpisah di struk.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Order yang benar-benar harus ditagihkan ke pelanggan: semua order
     * yang menempel ke sesi ini kecuali yang sudah dibatalkan.
     */
    public function billableOrders(): HasMany
    {
        return $this->orders()->billable();
    }

    /**
     * Order QR yang masih perlu tindakan kasir (dimasak atau diantar).
     */
    public function outstandingOrders(): HasMany
    {
        return $this->orders()->outstanding();
    }

    /**
     * Ringkasan order QR untuk badge di kartu grid.
     *
     * Menghitung dari relasi yang sudah di-eager-load kalau ada supaya layar
     * kasir tidak menambah query per kartu saat polling. `latest` dipakai
     * supaya badge menyebut pesanan terakhir yang masuk — itu yang sedang
     * ditunggu kasir, bukan yang pertama.
     *
     * @return array{count: int, preparing_count: int, latest: array<string, mixed>|null}
     */
    public function orderSummary(): array
    {
        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->outstandingOrders()->latest('id')->with('items.product')->get();

        $latest = $orders->sortByDesc('id')->first();

        return [
            'count' => $orders->count(),
            'preparing_count' => $orders->filter(fn (Order $order) => $order->status->isPreparing())->count(),
            'latest' => $latest === null ? null : [
                'id' => $latest->id,
                'code' => $latest->code,
                'status' => $latest->status->value,
                'status_label' => $latest->status->label(),
                'badge_class' => $latest->status->badgeClass(),
                'customer_name' => $latest->customer_name,
                'item_count' => $latest->itemCount(),
                'headline' => $latest->headline(),
            ],
        ];
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', RentalSessionStatus::RUNNING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RentalSessionStatus::COMPLETED);
    }

    public function isRunning(): bool
    {
        return $this->status === RentalSessionStatus::RUNNING;
    }

    /**
     * Sisa waktu dalam detik, dihitung server-side lalu dikoreksi drift di client.
     */
    public function remainingSeconds(): int
    {
        $endAt = $this->start_time->copy()->addMinutes($this->planned_minutes);

        return max(0, now()->diffInSeconds($endAt, false));
    }

    public function elapsedSeconds(): int
    {
        return (int) $this->start_time->diffInSeconds(now());
    }

    public function itemsTotal(): float
    {
        return (float) $this->items()->sum('subtotal');
    }

    public function ordersTotal(): float
    {
        return (float) $this->billableOrders()->sum('total_price');
    }

    /**
     * Total tagihan = biaya sewa + pesanan manual + pesanan QR meja.
     *
     * Dipakai untuk estimasi tagihan di layar kasir *sebelum* checkout. Saat
     * checkout, `RentalService` memakai angka ini di dalam satu transaksi dan
     * sekaligus menandai order QR-nya sebagai `COMPLETED` — jadi jangan
     * menjumlahkannya lagi lewat `ShiftService`, atau uang F&B terhitung dua
     * kali di rekap shift.
     */
    public function grandTotal(): float
    {
        return (float) $this->rental_fee + $this->itemsTotal() + $this->ordersTotal();
    }

    /**
     * Biru = masih berjalan, kuning = waktu habis.
     */
    public function isTimeUp(): bool
    {
        return $this->remainingSeconds() <= 0;
    }
}
