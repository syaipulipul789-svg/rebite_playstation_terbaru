<?php

namespace App\Http\Controllers;

use App\Enums\ProductCategory;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Http\Requests\SearchBookingStatusRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Product;
use App\Models\RatePackage;
use App\Models\Unit;
use App\Services\BookingService;
use App\Support\Money;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CustomerDisplayController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

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
            'bookingUnits' => $this->bookingUnits($units),
            'myBookings' => $this->myBookings(),
        ]);
    }

    /**
     * Proses form reservasi online dari halaman publik (tanpa login).
     * Bentrok jadwal dicek di BookingService terhadap sesi rental berjalan
     * dan booking confirmed lain pada konsol yang sama.
     */
    public function storeBooking(StoreBookingRequest $request): JsonResponse|RedirectResponse
    {
        $unit = Unit::findOrFail($request->integer('console_id'));
        $start = Carbon::parse($request->input('start_time'));
        $end = $start->copy()->addHours($request->integer('duration_hours'));

        $booking = $this->bookings->create(
            unit: $unit,
            customerName: $request->input('customer_name'),
            customerPhone: $request->input('customer_phone'),
            start: $start,
            end: $end,
            notes: $request->input('notes'),
        )->fresh('console');

        $this->rememberBookingToken($booking->public_token);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking anda telah diterima dan menunggu persetujuan kasir.',
                'booking_code' => $booking->bookingCode(),
                'console_name' => $booking->console?->name,
                'start_time_label' => $booking->start_time->format('d M Y H:i'),
                'end_time_label' => $booking->end_time->format('d M Y H:i'),
                'total_price_label' => Money::format($booking->total_price),
                'status' => $booking->customerStatus(),
            ], 201);
        }

        return back();
    }

    /**
     * Status booking milik session browser ini saja.
     *
     * Token publik (uuid) disimpan di session, bukan kode `BK-0007`: kode itu
     * diturunkan dari primary key berurutan sehingga bisa ditebak, sementara
     * token di sini tidak pernah muncul di halaman publik.
     */
    public function bookingStatus(): JsonResponse
    {
        return response()->json([
            'server_timestamp' => now()->getTimestampMs(),
            'bookings' => $this->myBookings(),
        ]);
    }

    /**
     * Halaman publik untuk cek status booking dari perangkat lain.
     *
     * Diperlukan karena section "Booking Saya" hanya menyimpan token di
     * session browser: pelanggan yang booking dari HP tidak bisa melihat
     * statusnya dari laptop, atau setelah cookie terhapus.
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

    /**
     * Data unit yang boleh dipesan (READY / BUSY) untuk modal form booking.
     * Untuk unit BUSY, `session_end_timestamp` = kapan slot berikutnya bebas,
     * dipakai sebagai jam mulai default di form.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function bookingUnits($units): Collection
    {
        return $units
            ->reject(fn (Unit $unit) => $unit->status === UnitStatus::MAINTENANCE)
            ->map(function (Unit $unit) {
                $session = $unit->runningSession;

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'type' => $unit->type,
                    'status' => $unit->status->value,
                    'hourly_rate' => (float) $unit->hourly_rate,
                    'is_free' => $unit->isFree(),
                    'session_end_timestamp' => $session !== null
                        ? $session->start_time->addMinutes($session->planned_minutes)->getTimestampMs()
                        : null,
                ];
            })
            ->values();
    }

    /**
     * Simpan token publik booking ke session browser, batasi 10 terbaru.
     */
    private function rememberBookingToken(string $token): void
    {
        $tokens = array_values(array_filter(
            array_unique([...(array) session('customer_booking_tokens', []), $token]),
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        session(['customer_booking_tokens' => array_slice($tokens, -10)]);
    }

    /**
     * Booking milik session ini, siap dikirim ke halaman status pelanggan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function myBookings(): array
    {
        $tokens = (array) session('customer_booking_tokens', []);

        if ($tokens === []) {
            return [];
        }

        return Booking::query()
            ->forTokens(array_values(array_filter($tokens, 'is_string')))
            ->with(['console', 'rentalSession'])
            ->get()
            ->sortByDesc(fn (Booking $booking) => $booking->start_time)
            ->map(fn (Booking $booking) => $this->transformBooking($booking))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBooking(Booking $booking): array
    {
        $status = $booking->customerStatus();
        $session = $booking->rentalSession;

        return [
            'code' => $booking->bookingCode(),
            'console_name' => $booking->console?->name,
            'console_code' => $booking->console?->code,
            'start_time_label' => $booking->start_time->format('d M Y H:i'),
            'end_time_label' => $booking->end_time->format('d M Y H:i'),
            'total_price_label' => Money::format($booking->total_price),
            'status' => $status,
            'remaining_seconds' => $status['is_occupied'] ? $session->remainingSeconds() : null,
        ];
    }
}
