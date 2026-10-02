<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\RatePackage;
use App\Models\RentalSession;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Snapshot grid monitoring unit untuk polling real-time.
     *
     * Field krusial:
     * - server_time  : acuan waktu server untuk menghilangkan drift jam client
     * - remaining_seconds : sisa waktu sesi dihitung ulang di server
     * - is_time_up   : memicu card kuning + alert di client
     *
     * `order_summary` sengaja dibatasi ke pesanan yang belum final: hanya
     * order milik sesi yang sedang berjalan yang muncul, jadi volumenya kecil
     * walau grid penuh. Rincian lengkap tetap diambil di `show()`.
     */
    public function index(Request $request): JsonResponse
    {
        $units = Unit::query()
            ->with([
                'runningSession' => fn ($q) => $q->with('user'),
                'runningSession.orders' => fn ($q) => $q
                    ->outstanding()
                    ->latest('id')
                    ->with('items.product'),
            ])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'server_timestamp' => now()->getTimestampMs(),
            'units' => $units->map(fn (Unit $unit) => $this->transform($unit))->values(),
        ]);
    }

    public function show(Unit $unit): JsonResponse
    {
        $unit->load([
            'runningSession' => fn ($q) => $q->with(['items.product', 'user']),
            'runningSession.orders' => fn ($q) => $q
                ->outstanding()
                ->latest('id')
                ->with('items.product'),
        ]);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'server_timestamp' => now()->getTimestampMs(),
            'unit' => $this->transform($unit, detailed: true),
        ]);
    }

    /**
     * Referensi data yang dibutuhkan form modal (paket jam, produk F&B).
     */
    public function meta(Unit $unit): JsonResponse
    {
        return response()->json([
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'type' => $unit->type,
                'hourly_rate' => (float) $unit->hourly_rate,
                'hourly_rate_label' => Money::format($unit->hourly_rate),
            ],
            'rate_packages' => RatePackage::query()
                ->active()
                ->forUnitType($unit->type)
                ->ordered()
                ->get()
                ->map(fn (RatePackage $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'duration_minutes' => $package->duration_minutes,
                    'duration_label' => $package->durationLabel(),
                    'price' => (float) $package->price,
                    'price_label' => Money::format($package->price),
                    'description' => $package->description,
                ]),
            'products' => Product::query()
                ->active()
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->value,
                    'category_label' => $product->category->label(),
                    'price' => (float) $product->price,
                    'price_label' => Money::format($product->price),
                    'stock' => $product->stock,
                    'is_low_stock' => $product->isLowStock(),
                ]),
        ]);
    }

    private function transform(Unit $unit, bool $detailed = false): array
    {
        $session = $unit->runningSession;
        $indicator = $unit->status->indicator();

        $data = [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type,
            'location' => $unit->location,
            'hourly_rate' => (float) $unit->hourly_rate,
            'hourly_rate_label' => Money::format($unit->hourly_rate),
            'status' => $unit->status->value,
            'status_label' => $unit->status->label(),
            'indicator' => $indicator,
            'can_start' => $unit->status === UnitStatus::READY,
            'session' => null,
            'order_summary' => null,
        ];

        if ($session !== null) {
            $remaining = $session->remainingSeconds();

            $data['session'] = [
                'id' => $session->id,
                'start_time' => $session->start_time->toIso8601String(),
                'start_timestamp' => $session->start_time->getTimestampMs(),
                'planned_minutes' => $session->planned_minutes,
                'planned_end_timestamp' => $session->start_time->getTimestampMs() + ($session->planned_minutes * 60 * 1000),
                'duration_minutes' => $session->duration_minutes,
                'extra_minutes' => $session->extra_minutes,
                'is_free_play' => $session->is_free_play,
                'package_name' => $session->package_name,
                'rental_fee' => (float) $session->rental_fee,
                'rental_fee_label' => Money::format($session->rental_fee),
                'remaining_seconds' => $remaining,
                'is_time_up' => $remaining <= 0,
                'elapsed_seconds' => $session->elapsedSeconds(),
                'cashier' => $session->user?->name,
            ];

            $data['order_summary'] = $session->orderSummary();

            if ($detailed) {
                $data['session']['items'] = $session->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'qty' => $item->qty,
                    'price' => (float) $item->price,
                    'price_label' => Money::format($item->price),
                    'subtotal' => (float) $item->subtotal,
                    'subtotal_label' => Money::format($item->subtotal),
                ]);

                $this->attachTotals($data['session'], $session);
            }
        }

        return $data;
    }

    /**
     * Rincian tagihan sesi: sewa + item manual + pesanan QR meja.
     *
     * Angka diambil dari model supaya estimasi di grid, modal detail, dan
     * receipt checkout tidak pernah berbeda. Hitungannya sudah termasuk
     * pesanan QR karena `RentalService` menetapkannya sebagai satu tagihan
     * dengan sewa.
     *
     * @param  array<string, mixed>  $target
     */
    private function attachTotals(array &$target, RentalSession $session): void
    {
        $itemsTotal = $session->itemsTotal();
        $ordersTotal = $session->ordersTotal();
        $grandTotal = Money::round($session->grandTotal());

        $target['items_total'] = $itemsTotal;
        $target['items_total_label'] = Money::format($itemsTotal);
        $target['orders_total'] = $ordersTotal;
        $target['orders_total_label'] = Money::format($ordersTotal);
        $target['grand_total'] = $grandTotal;
        $target['grand_total_label'] = Money::format($grandTotal);
    }
}
