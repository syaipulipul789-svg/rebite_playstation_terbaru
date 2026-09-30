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

            if (! in_array($locked->status, [OrderStatus::PENDING, OrderStatus::PLACED], true)) {
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
