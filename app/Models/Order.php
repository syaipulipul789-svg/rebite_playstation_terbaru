<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'token',
        'unit_id',
        'booking_id',
        'shift_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'status',
        'total_price',
        'payment_method',
        'notes',
        'placed_at',
        'settled_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_price' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'placed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::PENDING, OrderStatus::PLACED]);
    }

    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PLACED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    public function scopeForToken(Builder $query, string $token): Builder
    {
        return $query->where('token', $token);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Draft = keranjang yang belum dikirim ke kasir. Hanya draft yang boleh
     * dibatalkan otomatis tanpa persetujuan kasir.
     */
    public function isDraft(): bool
    {
        return $this->status === OrderStatus::PENDING;
    }

    public function totalPrice(): float
    {
        return (float) $this->total_price;
    }

    public function itemCount(): int
    {
        return (int) $this->items()->sum('qty');
    }

    /**
     * Cari item order untuk satu produk, dipakai saat scan barcode berulang
     * supaya produk yang sama digabung jadi satu baris.
     */
    public function findItemForProduct(int $productId): ?OrderItem
    {
        return $this->items()->where('product_id', $productId)->first();
    }
}
