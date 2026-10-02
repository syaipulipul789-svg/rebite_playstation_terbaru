<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Support\Money;
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
        'rental_session_id',
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

    public function rentalSession(): BelongsTo
    {
        return $this->belongsTo(RentalSession::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pesanan yang belum selesai: masih jadi pekerjaan dapur atau masih
     * menunggu pembayaran.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::PENDING, OrderStatus::PLACED]);
    }

    /**
     * Semua pesanan yang belum final, termasuk alur dapur.
     *
     * Berbeda dari `open()` yang hanya menyaring draft + pesanan barcode:
     * grid kasir perlu melihat pesanan QR yang sudah `PREPARING`/`SERVED`
     * supaya badge dan detail-nya ikut muncul.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::PENDING,
            OrderStatus::PLACED,
            OrderStatus::PREPARING,
            OrderStatus::SERVED,
        ]);
    }

    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::PLACED, OrderStatus::PREPARING, OrderStatus::SERVED]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    /**
     * Pesanan yang masih harus ikut ditagihkan di struk rental. Hanya
     * `CANCELLED` yang dikeluarkan — pesanan yang sudah dibayar terpisah
     * pun tetap sah karena uangnya sudah masuk rekap shift.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('status', '!=', OrderStatus::CANCELLED);
    }

    /**
     * Pesanan pemesanan mandiri dari QR meja.
     *
     * Bedanya dengan `booking()`: yang ini menempel ke sesi sewa yang sedang
     * berjalan, sehingga kasir tahu pesanan itu milik unit mana tanpa perlu
     * menebak dari nama pelanggan.
     */
    public function scopeForRentalSession(Builder $query, RentalSession|int $session): Builder
    {
        $id = $session instanceof RentalSession ? $session->getKey() : $session;

        return $query->where('rental_session_id', $id);
    }

    /**
     * Hanya pesanan QR meja yang pembayarannya ikut digabung ke struk rental.
     *
     * Pesanan hasil scan barcode punya `rental_session_id` null, jadi tetap
     * dihitung sebagai penjualan F&B mandiri oleh rekap shift.
     */
    public function scopeSessionLinked(Builder $query): Builder
    {
        return $query->whereNotNull('rental_session_id');
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

    /**
     * Pesanan yang pembayarannya digabung ke struk rental sesi yang sama.
     */
    public function isSettledWithSession(): bool
    {
        return $this->rental_session_id !== null
            && $this->status === OrderStatus::COMPLETED
            && $this->settled_at !== null;
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
     * Bentuk data pesanan untuk ditampilkan ke kasir.
     *
     * Dipakai oleh grid unit dan modal detail sesi supaya keduanya tidak
     * menampilkan bentuk data yang berbeda. Tidak menyertakan URL aksi —
     * URL itu milik controller, bukan data pesanan.
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'badge_class' => $this->status->badgeClass(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'total' => $this->totalPrice(),
            'total_label' => Money::format($this->totalPrice()),
            'item_count' => $this->itemCount(),
            'can_serve' => $this->status->isPreparing(),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'qty' => $item->qty,
                'subtotal_label' => Money::format($item->subtotal),
                'notes' => $item->notes,
            ])->all(),
        ];
    }

    /**
     * Nama item untuk badge kartu grid.
     *
     * Produk yang diulang ditulis "2x Indomie Goreng", sisanya diringkas
     * supaya badge tidak melebar di kartu kecil.
     */
    public function headline(): string
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 'Pesanan tanpa item';
        }

        $first = $items->first();
        $headline = $first->qty > 1
            ? "{$first->qty}x {$first->product?->name}"
            : (string) $first->product?->name;

        $rest = $items->count() - 1;

        return $rest > 0 ? $headline." +{$rest} item" : $headline;
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
