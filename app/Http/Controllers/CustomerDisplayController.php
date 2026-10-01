<?php

namespace App\Http\Controllers;

use App\Enums\ProductCategory;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Http\Requests\SearchBookingStatusRequest;
use App\Models\Booking;
use App\Models\Product;
use App\Models\RatePackage;
use App\Models\Unit;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman yang bisa dilihat tanpa login: landing page di `/` dan live monitor
 * layar TV di `/display`.
 *
 * Booking tidak ada di sini — pelanggan harus login dulu, dikerjakan di
 * {@see CustomerBookingController}.
 */
class CustomerDisplayController extends Controller
{
    /**
     * Halaman awal PUBLIK (tanpa login): ketersediaan unit, paket sewa,
     * dan menu snack/minuman.
     *
     * Perilaku lama `/` untuk user yang sudah login tetap dijaga: Owner ->
     * dashboard, kasir dengan shift aktif -> grid unit, kasir tanpa shift ->
     * input modal awal.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            if ($request->user()->isCustomer()) {
                return redirect()->route('customer.dashboard');
            }

            if ($request->user()->isOwner()) {
                return redirect()->route('owner.dashboard');
            }

            $hasOpenShift = $request->user()
                ->shifts()
                ->where('status', ShiftStatus::OPEN)
                ->exists();

            return redirect()->route($hasOpenShift ? 'units.index' : 'shift.start');
        }

        $units = Unit::query()
            ->with(['runningSession'])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $products = Product::query()->active()->get();

        return view('customer.index', [
            'units' => $units,
            'stats' => [
                'total' => $units->count(),
                'ready' => $units->where('status', UnitStatus::READY)->count(),
                'busy' => $units->where('status', UnitStatus::BUSY)->count(),
                'maintenance' => $units->where('status', UnitStatus::MAINTENANCE)->count(),
            ],
            'ratePackages' => RatePackage::query()->active()->ordered()->get(),
            'unitTypes' => $this->groupUnitTypes($units),
            'productGroups' => $this->groupProducts($products),
        ]);
    }

    /**
     * Halaman publik untuk cek status booking dari perangkat lain.
     *
     * Diperlukan karena dashboard pelanggan memakai session login: orang
     * yang memesan lewat HP tidak bisa melihat statusnya dari laptop, atau
     * setelah cookie terhapus.
     */
    public function checkStatus(): View
    {
        return view('customer.check-status', [
            'booking' => null,
            'error' => null,
        ]);
    }

    /**
     * Cari booking berdasarkan kode + nomor WhatsApp yang sama.
     *
     * Nomor WhatsApp ikut dicek supaya kode booking (yang bisa ditebak,
     * mis. BK-0001) tidak cukup untuk melihat detail orang lain. Kode yang
     * ada tapi nomornya beda diperlakukan sama dengan tidak ditemukan, agar
     * penyerang tidak bisa memetakan kode yang valid.
     */
    public function searchStatus(SearchBookingStatusRequest $request): View
    {
        $booking = null;

        if ($request->bookingId() !== null) {
            $booking = Booking::query()
                ->with(['console', 'rentalSession'])
                ->find($request->bookingId());
        }

        $matchesPhone = $booking !== null && in_array(
            Phone::normalize($booking->customer_phone),
            Phone::variants($request->input('customer_phone')),
            true,
        );

        return view('customer.check-status', [
            'booking' => $matchesPhone ? $booking : null,
            'error' => $matchesPhone ? null : 'Booking tidak ditemukan. Pastikan kode booking dan nomor WhatsApp yang dipakai saat membuat booking sudah benar.',
        ]);
    }

    /**
     * Live monitor layar TV rental: full screen, status per unit + timer
     * countdown real-time. Tanpa login.
     */
    public function liveDisplay(): View
    {
        return view('customer.display', [
            'payload' => $this->displayPayload(),
        ]);
    }

    /**
     * Snapshot JSON untuk polling halaman monitor (tiap ~10 detik). Alasan
     * endpoint terpisah: status & sisa waktu harus dihitung di server agar
     * jam lokal TV tidak membuat timer meleset.
     */
    public function displayData(): JsonResponse
    {
        return response()->json($this->displayPayload());
    }

    private function displayPayload(): array
    {
        $units = Unit::query()
            ->with(['runningSession'])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        return [
            'server_time' => now()->toIso8601String(),
            'server_timestamp' => now()->getTimestampMs(),
            'units' => $units
                ->map(fn (Unit $unit) => $this->transform($unit))
                ->values(),
        ];
    }

    private function transform(Unit $unit): array
    {
        $session = $unit->runningSession;

        $data = [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type,
            'hourly_rate_label' => Money::format($unit->hourly_rate),
            'status' => $unit->status->value,
            'status_label' => $unit->status->label(),
            'session' => null,
        ];

        if ($session !== null) {
            $remaining = $session->remainingSeconds();

            $data['session'] = [
                'id' => $session->id,
                'package_name' => $session->package_name,
                'planned_minutes' => $session->planned_minutes,
                'planned_end_timestamp' => $session->start_time->getTimestampMs() + ($session->planned_minutes * 60 * 1000),
                'remaining_seconds' => $remaining,
                'is_time_up' => $remaining <= 0,
            ];
        }

        return $data;
    }

    /**
     * Tarif per jam per tipe unit (PS4, PS5, VIP, Open Play) untuk katalog harga.
     *
     * @return Collection<int, array{type: string, rate_labels: Collection<int, string>}>
     */
    private function groupUnitTypes($units): Collection
    {
        return $units
            ->groupBy('type')
            ->map(function ($group, string $type) {
                $rates = $group
                    ->map(fn (Unit $unit) => $unit->isFree() ? 0 : (float) $unit->hourly_rate)
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(fn (float $rate) => $rate <= 0 ? 'Gratis' : Money::format($rate));

                return [
                    'type' => $type,
                    'rate_labels' => $rates,
                ];
            })
            ->values();
    }

    /**
     * Kelompokkan produk aktif per kategori (Snack & Minuman) untuk menu landing.
     *
     * @return Collection<int, array{category: ProductCategory, items: Collection<int, Product>}>
     */
    private function groupProducts($products): Collection
    {
        return collect(ProductCategory::cases())
            ->reject(fn (ProductCategory $category) => $category === ProductCategory::EXTRA)
            ->map(function (ProductCategory $category) use ($products) {
                return [
                    'category' => $category,
                    'items' => $products
                        ->where('category', $category)
                        ->sortBy('name')
                        ->values(),
                ];
            })
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }
}
