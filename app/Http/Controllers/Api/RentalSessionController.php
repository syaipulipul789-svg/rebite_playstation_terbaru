<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddRentalTimeRequest;
use App\Http\Requests\AddSessionItemRequest;
use App\Http\Requests\CompleteRentalRequest;
use App\Http\Requests\StartRentalRequest;
use App\Models\Order as OrderModel;
// Di-import sebagai OrderModel karena `Api\Order` sudah dipakai controller
// POS untuk pesanan walk-in.
use App\Models\RatePackage;
use App\Models\RentalSession;
use App\Models\Unit;
use App\Services\OrderService;
use App\Services\RentalService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RentalSessionController extends Controller
{
    public function __construct(
        private readonly RentalService $rental,
        private readonly OrderService $orders,
    ) {}

    /**
     * Card Hijau -> Modal Mulai Sewa.
     */
    public function store(StartRentalRequest $request): JsonResponse
    {
        $unit = Unit::findOrFail($request->integer('unit_id'));

        $package = $request->filled('rate_package_id')
            ? RatePackage::findOrFail($request->integer('rate_package_id'))
            : null;

        $session = $this->rental->start(
            unit: $unit,
            cashier: $request->user(),
            package: $package,
            openPlayMinutes: (int) ($request->input('open_play_minutes') ?? 0),
            isFreePlay: $request->boolean('is_free_play'),
        );

        return response()->json([
            'message' => "Sewa {$unit->name} dimulai.",
            'session' => $this->transform($session),
        ], 201);
    }

    /**
     * Card Merah -> Modal Detail Sewa.
     */
    public function show(RentalSession $rentalSession): JsonResponse
    {
        abort_unless($rentalSession->status !== RentalSessionStatus::CANCELLED, 404);

        // `billableOrders`, bukan `orders`: setelah checkout semua order QR
        // sesi ini berubah jadi COMPLETED, dan struk yang baru selesai itu
        // masih harus menampilkan pesanan yang dibayar pelanggan.
        $rentalSession->load([
            'unit',
            'items.product',
            'user',
            'shift',
            'orders' => fn ($q) => $q->billable()->latest('id')->with('items.product'),
        ]);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'server_timestamp' => now()->getTimestampMs(),
            'session' => $this->transform($rentalSession),
        ]);
    }

    /**
     * Tambah durasi / extra time.
     */
    public function extend(AddRentalTimeRequest $request, RentalSession $rentalSession): JsonResponse
    {
        try {
            $session = $this->rental->extend($rentalSession, $request->integer('extra_minutes'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Durasi ditambahkan.',
            'session' => $this->transform($session->load('unit')),
        ]);
    }

    /**
     * Tambah pesanan F&B.
     */
    public function storeItem(AddSessionItemRequest $request, RentalSession $rentalSession): JsonResponse
    {
        $item = $this->rental->addItem(
            $rentalSession,
            $request->integer('product_id'),
            $request->integer('qty'),
        );

        $rentalSession->load(['unit', 'items.product', 'user']);

        return response()->json([
            'message' => "{$item->product->name} ditambahkan.",
            'item' => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'qty' => $item->qty,
                'price_label' => Money::format($item->price),
                'subtotal_label' => Money::format($item->subtotal),
            ],
            'session' => $this->transform($rentalSession),
        ], 201);
    }

    /**
     * Selesaikan sewa & tutup billing.
     */
    public function complete(CompleteRentalRequest $request, RentalSession $rentalSession): JsonResponse
    {
        try {
            $session = $this->rental->complete(
                $rentalSession,
                PaymentMethod::from($request->string('payment_method')->toString()),
                $request->input('note'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $session->load([
            'unit',
            'items.product',
            'shift',
            'orders' => fn ($q) => $q->billable()->latest('id')->with('items.product'),
        ]);

        return response()->json([
            'message' => "Sewa {$session->unit->name} selesai.",
            'receipt' => $this->transform($session),
            'session' => $this->transform($session),
        ]);
    }

    /**
     * Batalkan sesi (mis. salah mulai / unit rusak).
     */
    public function cancel(Request $request, RentalSession $rentalSession): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $session = $this->rental->cancel($rentalSession, $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sewa dibatalkan dan stok dikembalikan.',
            'session' => $this->transform($session),
        ]);
    }

    /**
     * Tandai pesanan QR sudah diantar (dari grid unit).
     */
    public function serveOrder(OrderModel $order): JsonResponse
    {
        try {
            $served = $this->orders->markServed($order, request()->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Pesanan {$served->code} ditandai sudah diantar.",
            'order' => $served->toDisplayArray(),
        ]);
    }

    /**
     * Batalkan pesanan QR dan kembalikan stoknya.
     */
    public function cancelOrder(Request $request, OrderModel $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $cancelled = $this->orders->cancel(
                $order,
                $validated['reason'] ?? null,
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Pesanan {$cancelled->code} dibatalkan dan stok dikembalikan.",
            'order' => $cancelled->toDisplayArray(),
        ]);
    }

    private function transform(RentalSession $session): array
    {
        // Sesi di `show()` selalu sudah eager-load `orders`, tapi `store()`
        // masih memakai transform ini sebelum relasinya ada — jadi total
        // pesanan dihitung dari query, bukan dari collection yang belum dimuat.
        $ordersTotal = $session->ordersTotal();
        $grandTotal = Money::round($session->grandTotal());

        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'status_label' => $session->status->label(),
            'unit' => [
                'id' => $session->unit->id,
                'code' => $session->unit->code,
                'name' => $session->unit->name,
                'type' => $session->unit->type,
            ],
            'package_name' => $session->package_name,
            'is_free_play' => $session->is_free_play,
            'start_time' => $session->start_time->toIso8601String(),
            'end_time' => $session->end_time?->toIso8601String(),
            'start_timestamp' => $session->start_time->getTimestampMs(),
            'planned_end_timestamp' => $session->start_time->getTimestampMs() + ($session->planned_minutes * 60 * 1000),
            'planned_minutes' => $session->planned_minutes,
            'duration_minutes' => $session->duration_minutes,
            'extra_minutes' => $session->extra_minutes,
            'remaining_seconds' => $session->remainingSeconds(),
            'is_time_up' => $session->remainingSeconds() <= 0,
            'rental_fee' => (float) $session->rental_fee,
            'rental_fee_label' => Money::format($session->rental_fee),
            'items_total' => $session->itemsTotal(),
            'items_total_label' => Money::format($session->itemsTotal()),
            'orders_total' => $ordersTotal,
            'orders_total_label' => Money::format($ordersTotal),
            'grand_total' => $grandTotal,
            'grand_total_label' => Money::format($grandTotal),
            'payment_method' => $session->payment_method?->value,
            'payment_method_label' => $session->payment_method?->label(),
            'cashier' => $session->user?->name,
            'shift_id' => $session->shift_id,
            'items' => $session->relationLoaded('items')
                ? $session->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'qty' => $item->qty,
                    'price_label' => Money::format($item->price),
                    'subtotal_label' => Money::format($item->subtotal),
                ])
                : [],
            'order_summary' => $session->orderSummary(),
            'orders' => $session->relationLoaded('orders')
                ? $session->orders->map(fn (OrderModel $order) => [
                    ...$order->toDisplayArray(),
                    'serve_url' => route('api.orders.serve', $order),
                    'cancel_url' => route('api.orders.cancel', $order),
                ])->all()
                : [],
            'note' => $session->note,
        ];
    }
}
