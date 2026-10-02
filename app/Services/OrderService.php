<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RentalSession;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\Barcode;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class OrderService
{
    /**
     * Pesan tunggal yang dipakai halaman pemesanan mandiri supaya pelanggan
     * melihat alasan yang sama, baik saat halaman dibuka maupun saat gagal
     * mengirim pesanan.
     */
    public const INACTIVE_SESSION_MESSAGE = 'Unit sedang tidak aktif. Silakan hubungi kasir untuk memulai main.';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ShiftService $shifts,
    ) {}

    /**
     * Buka pesanan baru (status PENDING) untuk pelanggan yang memindai
     * barcode. `unit` / `booking` opsional — pemesan yang tidak menyewa
     * konsol tetap bisa memesan makanan, dan conversely pemesan konsol bisa
     * menautkan pesanan ke bookingnya lewat kode booking.
     */
    public function open(
        string $customerName,
        string $customerPhone,
        ?Unit $unit = null,
        ?Booking $booking = null,
        ?string $notes = null,
    ): Order {
        return DB::transaction(function () use ($customerName, $customerPhone, $unit, $booking, $notes) {
            $order = Order::create([
                'code' => $this->generateCode(),
                'token' => (string) Str::uuid(),
                'unit_id' => $unit?->id,
                'booking_id' => $booking?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'status' => OrderStatus::PENDING,
                'notes' => $notes,
            ]);

            return $order->load(['unit', 'booking', 'items.product']);
        });
    }

    /**
     * Pesanan milik pelanggan yang sedang aktif (masih bisa diedit) atau
     * sudah dikirim tapi belum dibayar — supaya halaman scan tidak
     * memaksa pelanggan membuat pesanan baru di tiap refresh.
     */
    public function findForCustomer(?string $token): ?Order
    {
        if ($token === null || $token === '') {
            return null;
        }

        return Order::query()
            ->forToken($token)
            ->open()
            ->with(['unit', 'booking', 'items.product'])
            ->latest('id')
            ->first();
    }

    /**
     * Buat pesanan dari menu QR meja dan langsung kirim ke dapur
     * (status `PREPARING`).
     *
     * Berbeda dengan alur barcode yang butuh beberapa langkah (buka -> pindai
     * -> place), pemesanan mandiri dikirim sebagai satu transaksi: kasir
     * tidak pernah melihat keranjang setengah jadi, dan stok produk yang
     * baru saja habis tidak sempat "dipesan lalu gagal" di dapet stok.
     *
     * @param  array<int, array{product_id: int, qty: int, notes?: string|null}>  $items
     */
    public function placeFromMenu(
        RentalSession $session,
        array $items,
        string $customerName,
        string $customerPhone,
        ?string $notes = null,
    ): Order {
        return DB::transaction(function () use ($session, $items, $customerName, $customerPhone, $notes) {
            $lockedSession = RentalSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $lockedSession->isRunning()) {
                throw ValidationException::withMessages([
                    'unit' => self::INACTIVE_SESSION_MESSAGE,
                ]);
            }

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Keranjang masih kosong.',
                ]);
            }
            $order = Order::create([
                'code' => $this->generateCode(),
                'token' => (string) Str::uuid(),
                'unit_id' => $lockedSession->unit_id,
                'rental_session_id' => $lockedSession->id,
                'shift_id' => $lockedSession->shift_id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'status' => OrderStatus::PREPARING,
                'notes' => $notes,
                'placed_at' => now(),
            ]);

            foreach ($items as $line) {
                $this->addMenuLine($order, $line);
            }

            $total = $this->recalculateTotal($order);

            $this->audit->record(
                event: AuditLog::EVENT_ORDER_PLACED,
                description: sprintf(
                    'Pesanan %s dari QR meja %s (%d item, total %s)',
                    $order->code,
                    $lockedSession->unit->code,
                    $order->itemCount(),
                    Money::format($total),
                ),
                context: [
                    'order_code' => $order->code,
                    'unit' => $lockedSession->unit->code,
                    'rental_session_id' => $lockedSession->id,
                    'source' => 'table_qr',
                ],
            );

            return $order->fresh(['unit', 'items.product']);
        });
    }

    /**
     * Tandai pesanan sudah diantar ke meja (status `SERVED`).
     *
     * Dipakai kasir setelah barang diantar. Pembayaran tetap terjadi di
     * checkout rental, jadi status ini hanya penanda untuk pelanggan.
     */
    public function markServed(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->isPreparing()) {
                throw new RuntimeException('Hanya pesanan yang sedang dimasak yang bisa ditandai sudah diantar.');
            }

            $locked->forceFill(['status' => OrderStatus::SERVED])->save();

            $this->audit->record(
                user: $actor,
                shift: $locked->shift,
                event: AuditLog::EVENT_ORDER_SETTLED,
                description: sprintf('Pesanan %s sudah diantar ke %s', $locked->code, $locked->unit?->code ?? '-'),
                context: ['order_code' => $locked->code, 'status' => OrderStatus::SERVED->value],
            );

            return $locked->fresh(['unit', 'items.product']);
        });
    }

    /**
     * Tandai seluruh pesanan QR meja milik sesi ini sebagai lunas, mengikuti
     * metode pembayaran yang dipakai untuk rental.
     *
     * Dipanggil dari dalam transaksi `RentalService::complete()`, bukan dari
     * controller, supaya "rental + F&B dibayar dalam satu struk" benar-benar
     * atomik: kalau gagal di tengah, tidak ada pesanan yang tercatat sudah
     * dibayar padahal struk rental-nya belum jadi.
     *
     * @return float total F&B yang ikut ditagihkan di struk rental
     */
    public function settleSessionOrders(RentalSession $session, User $cashier, PaymentMethod $paymentMethod): float
    {
        $total = 0.0;

        $orders = Order::query()
            ->forRentalSession($session)
            ->billable()
            ->where('status', '!=', OrderStatus::COMPLETED)
            ->lockForUpdate()
            ->get();

        foreach ($orders as $order) {
            $order->forceFill([
                'status' => OrderStatus::COMPLETED,
                'shift_id' => $session->shift_id ?? $order->shift_id,
                'user_id' => $cashier->id,
                'payment_method' => $paymentMethod,
                'settled_at' => now(),
            ])->save();

            $total += $order->totalPrice();
        }

        if ($orders->isNotEmpty()) {
            $this->audit->record(
                user: $cashier,
                shift: $session->shift,
                event: AuditLog::EVENT_ORDER_SETTLED,
                description: sprintf(
                    '%d pesanan QR meja dilunasi bersama rental %s (%s), total %s',
                    $orders->count(),
                    $session->unit?->code ?? '-',
                    $paymentMethod->label(),
                    Money::format($total),
                ),
                context: [
                    'unit' => $session->unit?->code,
                    'rental_session_id' => $session->id,
                    'order_codes' => $orders->pluck('code')->all(),
                    'total' => Money::round($total),
                    'payment_method' => $paymentMethod->value,
                ],
            );
        }

        return Money::round($total);
    }

    /**
     * Tambah produk hasil pindai barcode. Barcode yang sama dipindai dua
     * kali akan menaikkan qty baris yang sudah ada, bukan membuat baris
     * baru, supaya keranjang pelanggan tetap rapi.
     */
    public function addByBarcode(Order $order, string $rawBarcode, int $qty = 1): OrderItem
    {
        $barcode = Barcode::normalize($rawBarcode);

        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode tidak terbaca. Arahkan kamera ke label produk.',
            ]);
        }

        if ($qty <= 0) {
            $qty = 1;
        }

        return DB::transaction(function () use ($order, $barcode, $qty) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'barcode' => 'Pesanan sudah dikirim ke kasir dan tidak bisa ditambah.',
                ]);
            }

            $product = Product::query()->lockForUpdate()->where('barcode', $barcode)->first();

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'barcode' => "Barcode {$barcode} tidak terdaftar atau produknya sudah nonaktif.",
                ]);
            }

            $item = $locked->findItemForProduct($product->id);
            $targetQty = ($item?->qty ?? 0) + $qty;

            if ($product->stock < $targetQty) {
                throw ValidationException::withMessages([
                    'barcode' => "Stok {$product->name} tidak cukup (tersisa {$product->stock}).",
                ]);
            }

            $subtotal = Money::round((float) $product->price * $targetQty);

            if ($item === null) {
                $item = OrderItem::create([
                    'order_id' => $locked->id,
                    'product_id' => $product->id,
                    'qty' => $targetQty,
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ]);
            } else {
                $item->forceFill(['qty' => $targetQty, 'subtotal' => $subtotal])->save();
            }

            $product->decrement('stock', $qty);

            $this->recalculateTotal($locked);

            return $item->fresh('product');
        });
    }

    /**
     * Ubah jumlah satu baris pesanan. qty 0 menghapus baris dan
     * mengembalikan stoknya.
     */
    public function changeQty(Order $order, OrderItem $item, int $qty): Order
    {
        return DB::transaction(function () use ($order, $item, $qty) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->isEditable()) {
                throw new RuntimeException('Pesanan sudah dikirim ke kasir dan tidak bisa diubah.');
            }

            $lockedItem = OrderItem::query()
                ->where('order_id', $locked->id)
                ->lockForUpdate()
                ->findOrFail($item->id);

            $product = Product::query()->lockForUpdate()->findOrFail($lockedItem->product_id);
            $currentQty = $lockedItem->qty;

            if ($qty <= 0) {
                $product->increment('stock', $currentQty);
                $lockedItem->delete();
            } else {
                $delta = $qty - $currentQty;

                if ($delta > 0 && $product->stock < $delta) {
                    throw ValidationException::withMessages([
                        'qty' => "Stok {$product->name} tidak cukup (tersisa {$product->stock}).",
                    ]);
                }

                if ($delta !== 0) {
                    $delta > 0
                        ? $product->decrement('stock', $delta)
                        : $product->increment('stock', abs($delta));
                }

                $lockedItem->forceFill([
                    'qty' => $qty,
                    'subtotal' => Money::round((float) $lockedItem->price * $qty),
                ])->save();
            }

            $this->recalculateTotal($locked);

            return $locked->fresh(['unit', 'items.product']);
        });
    }

    public function removeItem(Order $order, OrderItem $item): Order
    {
        return $this->changeQty($order, $item, 0);
    }

    /**
     * Pelanggan mengirim pesanan ke kasir. Status berhenti PENDING sehingga
     * item terkunci dan kasir bisa mengetahuinya lewat halaman POS.
     */
    public function place(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->isEditable()) {
                throw new RuntimeException('Pesanan sudah dikirim sebelumnya.');
            }

            if ($locked->items()->doesntExist()) {
                throw ValidationException::withMessages([
                    'items' => 'Pesanan masih kosong. Pindai minimal satu produk dulu.',
                ]);
            }

            $locked->forceFill([
                'status' => OrderStatus::PLACED,
                'placed_at' => now(),
            ])->save();

            $this->audit->record(
                event: AuditLog::EVENT_ORDER_PLACED,
                description: sprintf(
                    'Pesanan %s dikirim pelanggan (%d item, total %s)',
                    $locked->code,
                    $locked->itemCount(),
                    Money::format($this->recalculateTotal($locked)),
                ),
                context: [
                    'order_code' => $locked->code,
                    'unit' => $locked->unit?->code,
                ],
            );

            return $locked->fresh(['unit', 'booking', 'items.product']);
        });
    }

    /**
     * Kasir menerima pembayaran. Order dikunci ke shift kasir yang sedang
     * berjalan supaya uangnya masuk rekap shift yang benar.
     */
    public function settle(Order $order, User $cashier, PaymentMethod $paymentMethod, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $cashier, $paymentMethod, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->rental_session_id !== null) {
                throw new RuntimeException(
                    'Pesanan QR meja dibayar otomatis saat rental di-checkout, bukan satu per satu di halaman ini.',
                );
            }

            if ($locked->status !== OrderStatus::PLACED) {
                throw new RuntimeException('Pesanan ini sudah diproses atau belum dikirim pelanggan.');
            }

            $shift = Shift::query()
                ->where('user_id', $cashier->id)
                ->where('status', ShiftStatus::OPEN)
                ->latest('start_time')
                ->lockForUpdate()
                ->first();

            $total = $this->recalculateTotal($locked);

            $locked->forceFill([
                'status' => OrderStatus::COMPLETED,
                'shift_id' => $shift?->id,
                'user_id' => $cashier->id,
                'payment_method' => $paymentMethod,
                'settled_at' => now(),
                'notes' => $note ?? $locked->notes,
            ])->save();

            $this->audit->record(
                user: $cashier,
                shift: $shift,
                event: AuditLog::EVENT_ORDER_SETTLED,
                description: sprintf(
                    'Pesanan %s dibayar (%s), total %s',
                    $locked->code,
                    $paymentMethod->label(),
                    Money::format($total),
                ),
                context: [
                    'order_code' => $locked->code,
                    'total' => $total,
                    'payment_method' => $paymentMethod->value,
                ],
            );

            if ($shift !== null) {
                $this->shifts->recalculateRevenue($shift);
            }

            return $locked->fresh(['unit', 'booking', 'items.product', 'shift']);
        });
    }

    /**
     * Batalkan pesanan: stok produk yang sempat dipesan dikembalikan.
     *
     * Baris item TIDAK dihapus supaya histori "apa saja yang dipesan lalu
     * dibatalkan" masih bisa ditelusuri lewat audit & laporan.
     */
    public function cancel(Order $order, ?string $reason = null, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->isOutstanding()) {
                throw new RuntimeException('Pesanan ini sudah diproses sebelumnya.');
            }

            $restored = 0;

            foreach ($locked->items()->with('product')->get() as $item) {
                $item->product?->increment('stock', $item->qty);
                $restored += $item->qty;
            }

            $locked->forceFill([
                'status' => OrderStatus::CANCELLED,
                'total_price' => 0,
                'notes' => $reason !== null ? $reason : $locked->notes,
            ])->save();

            $this->audit->record(
                user: $actor,
                event: AuditLog::EVENT_ORDER_CANCELLED,
                description: sprintf(
                    'Pesanan %s dibatalkan (%d item stok dikembalikan)%s',
                    $locked->code,
                    $restored,
                    $reason !== null ? ': '.$reason : '',
                ),
                context: [
                    'order_code' => $locked->code,
                    'restored_qty' => $restored,
                ],
            );

            return $locked->fresh(['unit', 'booking', 'items.product']);
        });
    }

    /**
     * Tambah satu baris item pesanan menu (dipakai oleh alur QR meja).
     *
     * Baris dengan produk yang sama digabung selama catatannya sama, tapi
     * tetap dipisah kalau catatan berbeda: "Nasi Goreng 2,pedas" dan
     * "Nasi Goreng 1,tidak pedas" adalah dua permintaan berbeda dan kasir
     * harus bisa membacanya terpisah.
     *
     * @param  array{product_id: int, qty: int, notes?: string|null}  $line
     */
    private function addMenuLine(Order $order, array $line): OrderItem
    {
        $qty = max(1, (int) ($line['qty'] ?? 1));
        $notes = filled($line['notes'] ?? null) ? trim((string) $line['notes']) : null;

        $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'items' => "Produk {$product->name} sudah nonaktif.",
            ]);
        }

        $item = $order->items()
            ->where('product_id', $product->id)
            ->where(function ($query) use ($notes) {
                $notes === null
                    ? $query->whereNull('notes')
                    : $query->where('notes', $notes);
            })
            ->lockForUpdate()
            ->first();

        $targetQty = ($item?->qty ?? 0) + $qty;

        if ($product->stock < $qty) {
            throw ValidationException::withMessages([
                'items' => "Stok {$product->name} tidak cukup (tersedia {$product->stock}).",
            ]);
        }

        $subtotal = Money::round((float) $product->price * $targetQty);

        if ($item === null) {
            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'qty' => $targetQty,
                'price' => $product->price,
                'subtotal' => $subtotal,
                'notes' => $notes,
            ]);
        } else {
            $item->forceFill(['qty' => $targetQty, 'subtotal' => $subtotal])->save();
        }

        $product->decrement('stock', $qty);

        return $item;
    }

    /**
     * Hitung ulang total pesanan dari baris item terkini.
     *
     * @return float total dalam rupiah
     */
    private function recalculateTotal(Order $order): float
    {
        $total = Money::round((float) $order->items()->sum('subtotal'));

        $order->forceFill(['total_price' => $total])->save();

        return $total;
    }

    /**
     * Kode pesanan pendek & mudah dibacakan kasir, mis. "ORD-7F3K".
     */
    private function generateCode(): string
    {
        do {
            $code = 'ORD-'.Str::upper(Str::random(4));
        } while (Order::query()->where('code', $code)->exists());

        return $code;
    }
}
