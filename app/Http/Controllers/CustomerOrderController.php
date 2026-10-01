<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\UnitStatus;
use App\Http\Requests\ScanBarcodeRequest;
use App\Http\Requests\StartCustomerOrderRequest;
use App\Http\Requests\UpdateOrderItemQuantityRequest;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Unit;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Halaman pemesanan menu pelanggan lewat pemindai barcode.
 *
 * Publik (tanpa login) seperti halaman landing & monitor. Pelanggan
 * Pelanggan diidentifikasi lewat `orders.token` yang disimpan di session
 * browser — token inilah yang jadi kunci semua endpoint di bawah, jadi
 * order satu pelanggan tidak bisa dibaca/diubah pelanggan lain.
 */
class CustomerOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Halaman scan. Kalau pelanggan sudah punya pesanan aktif di session,
     * langsung dibuka keranjangnya supaya tidak perlu buat pesanan baru
     * di tiap kunjungan.
     */
    public function index(Request $request): View
    {
        $order = $this->orders->findForCustomer($request->session()->get('customer_order_token'));

        return view('customer.order', [
            'order' => $order,
            'orderPayload' => $order !== null ? $this->transform($order) : null,
            'units' => Unit::query()
                ->whereIn('status', [UnitStatus::READY, UnitStatus::BUSY])
                ->orderBy('type')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'status']),
        ]);
    }

    /**
     * Buka pesanan baru lalu arahkan ke halaman scan. Dipakai saat
     * pelanggan belum punya pesanan aktif.
     */
    public function store(StartCustomerOrderRequest $request): RedirectResponse
    {
        // Buang keranjang draft lama supaya tidak menumpuk stok terkunci
        // dari percobaan yang tidak pernah diselesaikan pelanggan.
        // HANYA draft: pesanan yang sudah dikirim ke kasir (PLACED) tidak
        // boleh dibatalkan diam-diem hanya karena pelanggan membuka order
        // baru di browser yang sama — uang yang menunggu pembayaran akan
        // hilang beserta stoknya.
        $stale = $this->orders->findForCustomer($request->session()->get('customer_order_token'));
        if ($stale !== null && $stale->isDraft()) {
            $this->orders->cancel($stale, 'Pesanan draft diganti pelanggan.');
        }

        $order = $this->orders->open(
            customerName: $request->string('customer_name')->toString(),
            customerPhone: $request->string('customer_phone')->toString(),
            unit: $request->filled('unit_id') ? Unit::findOrFail($request->integer('unit_id')) : null,
            booking: $this->resolveBooking($request, $request->input('booking_code')),
            notes: $request->input('notes'),
        );

        $request->session()->put('customer_order_token', $order->token);

        return redirect()
            ->route('customer.order.index')
            ->with('success', "Pesanan {$order->code} siap. Pindai barcode produk yang ingin dipesan.");
    }

    /**
     * Tambahkan produk hasil pindai barcode. Dipanggil otomatis setiap
     * barcode terbaca oleh kamera, jadi balasan JSON-nya dipakai untuk
     * update keranjang tanpa reload halaman.
     */
    public function scan(ScanBarcodeRequest $request, string $token): JsonResponse
    {
        $order = $this->authorizeOrder($request, $token);

        $item = $this->orders->addByBarcode(
            order: $order,
            rawBarcode: $request->string('barcode')->toString(),
            qty: $request->integer('qty'),
        );

        return response()->json([
            'message' => "{$item->product->name} ditambahkan.",
            'order' => $this->transform($order->fresh(['unit', 'items.product'])),
        ], 201);
    }

    public function updateItem(UpdateOrderItemQuantityRequest $request, string $token, OrderItem $item): JsonResponse
    {
        $order = $this->authorizeItem($request, $token, $item);

        $order = $this->orders->changeQty($order, $item, $request->integer('qty'));

        return response()->json([
            'message' => 'Keranjang diperbarui.',
            'order' => $this->transform($order),
        ]);
    }

    public function destroyItem(Request $request, string $token, OrderItem $item): JsonResponse
    {
        $order = $this->authorizeItem($request, $token, $item);

        $order = $this->orders->removeItem($order, $item);

        return response()->json([
            'message' => 'Item dihapus dari pesanan.',
            'order' => $this->transform($order),
        ]);
    }

    /**
     * Pelanggan mengirim pesanan ke kasir. Setelah ini item terkunci.
     */
    public function place(Request $request, string $token): JsonResponse
    {
        $order = $this->authorizeOrder($request, $token);

        try {
            $order = $this->orders->place($order);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Pesanan {$order->code} terkirim ke kasir. Tunjukkan kode ini saat bayar.",
            'order' => $this->transform($order),
        ]);
    }

    /**
     * Snapshot pesanan untuk polling: pelanggan_left dibuat supaya halaman
     * otomatis berubah jadi "sudah dibayar" begitu kasir menutup order.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $order = $this->authorizeOrder($request, $token);

        return response()->json([
            'server_timestamp' => now()->getTimestampMs(),
            'order' => $this->transform($order->loadMissing(['unit', 'booking', 'items.product'])),
        ]);
    }

    /**
     * Pastikan order dengan token ini milik session pelanggan yang sedang
     * request — mencegah pelanggan A membaca/mengubah pesanan B hanya
     * karena tahu URL-nya.
     */
    private function authorizeOrder(Request $request, string $token): Order
    {
        if (! hash_equals((string) $request->session()->get('customer_order_token'), $token)) {
            abort(403, 'Pesanan ini bukan milik sesi Anda.');
        }

        return Order::query()
            ->forToken($token)
            ->with(['unit', 'items.product'])
            ->firstOrFail();
    }

    private function authorizeItem(Request $request, string $token, OrderItem $item): Order
    {
        $order = $this->authorizeOrder($request, $token);

        abort_unless($item->order_id === $order->id, 404);

        return $order;
    }

    /**
     * Pelanggan boleh menautkan pesanan ke booking miliknya dengan kode
     * booking (mis. "BK-0007").
     *
     * Kode `BK-####` diturunkan dari primary key berurutan, jadi tanpa cek
     * kepemilikan siapa pun bisa menebak booking orang lain lalu attach
     * pesanannya. Karena itu booking harus milik akun pelanggan yang sedang
     * login. Selain itu ditolak diam-diam agar pemeriksaannya tidak bisa
     * dipetakan.
     */
    private function resolveBooking(Request $request, ?string $code): ?Booking
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $normalized = strtoupper(trim($code));

        if (preg_match('/^BK-0*(\d+)$/', $normalized, $matches) !== 1) {
            return null;
        }

        $customer = $request->user();

        // Booking hanya bisa dibuat dari akun pelanggan, jadi tanpa sesi
        // pelanggan tidak ada yang boleh ditautkan.
        if ($customer === null || ! $customer->isCustomer()) {
            return null;
        }

        return Booking::query()
            ->forCustomer($customer)
            ->whereKey((int) $matches[1])
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Order $order): array
    {
        return [
            'code' => $order->code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'is_editable' => $order->isEditable(),
            'total_label' => Money::format($order->total_price),
            'unit_code' => $order->unit?->code,
            'unit_name' => $order->unit?->name,
            'item_count' => $order->itemCount(),
            'items' => $order->items
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'qty' => $item->qty,
                    'price_label' => Money::format($item->price),
                    'subtotal_label' => Money::format($item->subtotal),
                ])
                ->values()
                ->all(),
        ];
    }
}
