<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_token',
        'console_id',
        'rental_session_id',
        'user_id',
        'confirmed_by',
        'customer_name',
        'customer_phone',
        'start_time',
        'end_time',
        'status',
        'confirmed_at',
        'total_price',
        'notes',
    ];

    protected static function booted(): void
    {
        // Token publik adalah satu-satunya kunci yang dipakai pelanggan untuk
        // melihat status booking-nya, jadi harus selalu ada tanpa bergantung
        // pada pemanggil.
        static::creating(function (self $booking): void {
            $booking->public_token ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'confirmed_at' => 'datetime',
            'total_price' => 'decimal:2',
            'status' => BookingStatus::class,
        ];
    }

    public function console(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'console_id');
    }

    public function rentalSession(): BelongsTo
    {
        return $this->belongsTo(RentalSession::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Akun pelanggan yang membuat booking ini. `null` untuk booking yang
     * belum terhubung ke akun (data lama atau diinput kasir manual).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeStatus(Builder $query, BookingStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForCustomer(Builder $query, User $customer): Builder
    {
        return $query->where('user_id', $customer->id);
    }

    /**
     * Booking yang sedang memegang slot waktu di unitnya, sehingga pelanggan
     * lain tidak bisa membooking jam yang sama.
     *
     * PENDING ikut dihitung supaya dua orang tidak bisa/antre sama-sama
     * memesan slot yang sama lalu saling gagal saat kasir mengonfirmasi.
     * Booking yang jamnya sudah lewat tidak dihitung supaya unit tidak
     * terkunci selamanya oleh booking yang tidak pernah sempat disetujui.
     */
    public function scopeHoldingSlot(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
            ->where('end_time', '>', now());
    }

    public function isPending(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    /**
     * Booking ini sedang mengunci slot waktu unitnya, jadi pelanggan
     * lain tidak boleh memakai rentang waktu yang sama.
     */
    public function holdsSlot(): bool
    {
        return in_array($this->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)
            && $this->end_time->isFuture();
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::CONFIRMED;
    }

    /**
     * Booking yang ditampilkan ke kasir di layar POS dan diucapkan pelanggan.
     * Sengaja TIDAK bisa dipakai sebagai kunci pencarian publik: nilainya
     * diturunkan dari primary key berurutan sehingga bisa ditebak.
     */
    public function bookingCode(): string
    {
        return 'BK-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Status yang ditampilkan ke pelanggan di halaman publik.
     *
     * @return array{value: string, label: string, hint: string, badge_class: string, is_occupied: bool}
     */
    public function customerStatus(): array
    {
        $isOccupied = $this->hasRunningSession();

        return [
            'value' => $this->status->value,
            'label' => $this->status->customerLabel($isOccupied),
            'hint' => $this->status->customerHint($isOccupied),
            'badge_class' => $this->status->badgeClass($isOccupied),
            'is_occupied' => $isOccupied,
        ];
    }

    public function durationHours(): int
    {
        return max(1, (int) $this->start_time->diffInHours($this->end_time));
    }

    public function durationMinutes(): int
    {
        return max(1, (int) $this->start_time->diffInMinutes($this->end_time));
    }

    /**
     * Booking sudah menghasilkan sesi rental yang sedang berjalan, artinya
     * unitnya terpakai dan tampilannya sudah "Terisi".
     */
    public function hasRunningSession(): bool
    {
        return $this->rentalSession !== null && $this->rentalSession->isRunning();
    }

    /**
     * Nomor tujuan `wa.me`, atau `null` kalau nomor pelanggan tidak bisa
     * dipakai untuk chat WhatsApp.
     */
    public function whatsappNumber(): ?string
    {
        return Phone::whatsappNumber($this->customer_phone);
    }

    /**
     * Pesan konfirmasi yang diisi otomatis di WhatsApp pelanggan.
     */
    public function whatsappApprovalMessage(): string
    {
        return sprintf(
            'Halo %s, booking Anda dengan Kode %s untuk unit %s pada jam %s telah DISETUJUI oleh Kasir Rebite Playstation. Silakan cek status di web kami. Terima kasih!',
            $this->customer_name,
            $this->bookingCode(),
            $this->console?->name ?? '-',
            $this->start_time->format('d M Y H:i'),
        );
    }

    /**
     * Tautan `wa.me` siap klik untuk dikirim kasir lewat WhatsApp Web atau
     * aplikasihp. Mengembalikan `null` kalau nomor pelanggan tidak valid
     * supaya kasir tidak mengarahkan pelanggan ke chat yang gagal dibuka.
     */
    public function whatsappApprovalUrl(): ?string
    {
        $number = $this->whatsappNumber();

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->whatsappApprovalMessage());
    }
}
