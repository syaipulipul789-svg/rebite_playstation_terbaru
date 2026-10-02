<?php

namespace App\Http\Controllers;

use App\Enums\ProductCategory;
use App\Http\Requests\StoreTableOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Unit;
use App\Services\OrderService;
use App\Support\Money;
use App\Support\TableQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pemesanan mandiri dari QR meja: /m/{unit_code}.
 *
 * Semua route di sini publik tanpa login, karena yang memindai QR adalah
 * pelanggan yang sedang bermain. Kode unit sendiri bukan rahasia, jadi yang
 * menjaga keamanan justru dua hal di bawah ini:
 *
 * 1. Menu hanya terbuka kalau unit punya `runningSession`. QR yang hilang
 *    dari meja tidak cukup untuk memesan karena tidak ada sesi aktif.
 * 2. Pelacakan pesanan memakai `orders.token` (UUID acak) di URL, jadi
 *    pelanggan lain tidak bisa menebak status pesanan orang.
 */
class TableOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * Halaman menu + keranjang untuk satu unit.
     */
    public function show(string $unitCode): View
    {
        $unit = $this->findUnit($unitCode);

        if ($unit === null) {
            return view('table-order.unknown', ['unitCode' => TableQr::normalizeCode($unitCode)]);
        }

        $session = $unit->runningSession;

        if ($session === null) {
            return view('table-order.inactive', ['unit' => $unit]);
        }

        return view('table-order.show', [
            'unit' => $unit,
            'session' => $session,
            'menu' => $this->menu(),
            'storeUrl' => route('table.order.store', ['unit_code' => $unit->code]),
        ]);
    }

    /**
     * Terima pesanan dari keranjang, lalu arahkan ke halaman pelacakan.
     *
     * Dipakai dua cara: `fetch` dari Alpine (butuh JSON) dan submit form
     * biasa sebagai cadangan kalau JS tidak jalan. Karena `axios` sudah
     * mengirim `Accept: application/json`, `expectsJson()` membedakan keduanya
     * tanpa param khusus.
     */
    public function store(StoreTableOrderRequest $request, string $unitCode): RedirectResponse|JsonResponse
    {
        $unit = $this->findUnit($unitCode);

        $session = $unit?->runningSession;

        if ($session === null) {
            // Dilewatkan sebagai exception (bukan redirect) supaya perilakunya
            // sama untuk JSON dan form biasa: JSON dapat 422 dengan pesan,
            // form biasa dapat redirect balik ke menu dengan pesan yang sama.
            throw ValidationException::withMessages([
                'unit' => OrderService::INACTIVE_SESSION_MESSAGE,
            ]);
        }

        $order = $this->orders->placeFromMenu(
            $session,
            $request->validated()['items'],
            $request->string('customer_name')->toString(),
            $request->string('customer_phone')->toString(),
            $this->cleanNote($request->input('notes')),
        );

        $target = route('table.order.tracking', [
            'unit_code' => $unit->code,
            'token' => $order->token,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $target]);
        }

        return redirect()->to($target);
    }

    /**
     * Halaman sukses + status pesanan yang di-poll berkala.
     */
    public function tracking(string $unitCode, string $token): View|RedirectResponse
    {
        $order = $this->findOrder($this->findUnit($unitCode), $token);

        if ($order === null) {
            return redirect()->route('table.order.show', ['unit_code' => TableQr::normalizeCode($unitCode)]);
        }

        return view('table-order.tracking', [
            'unit' => $order->unit,
            'session' => $order->rentalSession,
            'order' => $order,
            'statusUrl' => route('table.order.status', [
                'unit_code' => $order->unit->code,
                'token' => $order->token,
            ]),
            'initialStatus' => $this->statusPayload($order),
        ]);
    }

    /**
     * Endpoint polling status pesanan.
     */
    public function status(string $unitCode, string $token): JsonResponse
    {
        $order = $this->findOrder($this->findUnit($unitCode), $token);

        if ($order === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        return response()->json($this->statusPayload($order));
    }

    /**
     * Bentuk data status yang sama untuk render awal dan hasil polling, jadi
     * halaman tidak perlu tahu dari mana datanya.
     *
     * @return array<string, mixed>
     */
    private function statusPayload(Order $order): array
    {
        return [
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'badge_class' => $order->status->badgeClass(),
            'total' => Money::format($order->totalPrice()),
            'code' => $order->code,
            'item_count' => $order->itemCount(),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product?->name ?? '-',
                'qty' => $item->qty,
                'notes' => $item->notes,
                'subtotal_label' => Money::format((float) $item->subtotal),
            ])->all(),
            'is_final' => $order->status->isPaid() || $order->status->isCancelled(),
        ];
    }

    /**
     * Cari unit berdasarkan kode yang sudah dinormalisasi huruf besar, jadi
     * query tetap memakai index `code` (bukan `UPPER(code)` yang full scan).
     */
    private function findUnit(string $unitCode): ?Unit
    {
        $code = TableQr::normalizeCode($unitCode);

        if ($code === '') {
            return null;
        }

        return Unit::query()
            ->with('runningSession')
            ->where('code', $code)
            ->first();
    }

    /**
     * Pesanan hanya boleh dibaca lewat unit yang sama dengan tempat QR-nya
     * dipindai. Tanpa ini, UUID yang bocor (mis. dari riwayat browser atau
     * screenshot) bisa dipakai membuka status pesanan unit lain.
     */
    private function findOrder(?Unit $unit, string $token): ?Order
    {
        if ($unit === null || $token === '') {
            return null;
        }

        return Order::query()
            ->forToken($token)
            ->where('unit_id', $unit->id)
            ->with(['unit', 'rentalSession', 'items.product'])
            ->first();
    }

    /**
     * Menu F&B yang dikelompokkan per kategori, hanya produk aktif dan yang
     * masih punya stok.
     *
     * Produk stok 0 sengaja disembunyikan: pelanggan tidak perlu tahu barang
     * yang memang tidak ada, dan satu kategori yang kosong tidak menyisakan
     * ruang kosong yang membingungkan.
     *
     * @return array<int, array{label: string, products: array<int, array<string, mixed>>}>
     */
    private function menu(): array
    {
        $products = Product::query()
            ->active()
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'price', 'stock', 'low_stock_threshold'])
            ->groupBy(fn (Product $product) => $product->category->value);

        $groups = [];

        // Iterasi enum, bukan hasil groupBy, supaya urutan menu sama dengan
        // halaman kasir dan tidak ikut berubah kalau nama produk diurutkan.
        foreach (ProductCategory::menuGroups() as $category) {
            $grouped = $products->get($category->value);

            if ($grouped === null || $grouped->isEmpty()) {
                continue;
            }

            $groups[] = [
                'label' => $category->label(),
                'products' => $grouped->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $product->stock,
                    // Angka mentah ikut dikirim karena Alpine menjumlahkan
                    // subtotal di perangkat; `price_label` saja tidak bisa
                    // dijumlahkan.
                    'price' => (float) $product->price,
                    'price_label' => Money::format((float) $product->price),
                    'low_stock' => $product->stock <= $product->low_stock_threshold,
                ])->all(),
            ];
        }

        return $groups;
    }

    private function cleanNote(mixed $note): ?string
    {
        $note = is_string($note) ? trim($note) : '';

        return $note === '' ? null : $note;
    }
}
