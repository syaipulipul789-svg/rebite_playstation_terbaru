<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Unit;
use App\Services\BookingService;
use App\Support\CustomerAvailability;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Area pelanggan yang wajib login: daftar unit lengkap dengan statusnya,
 * form booking, dan riwayat booking milik akun yang sedang masuk.
 *
 * Identitas pelanggan diambil dari akun (bukan dari form), jadi nama dan
 * nomor WhatsApp yang tercatat selalu bisa dihubungi kasir.
 */
class CustomerBookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): View
    {
        $units = Unit::query()
            ->with(['runningSession'])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        return view('customer.dashboard', [
            'units' => $units,
            'stats' => [
                'total' => $units->count(),
                'ready' => $units->where('status', UnitStatus::READY)->count(),
                'busy' => $units->where('status', UnitStatus::BUSY)->count(),
                'maintenance' => $units->where('status', UnitStatus::MAINTENANCE)->count(),
            ],
            'bookingUnits' => CustomerAvailability::bookingUnits($units),
            'myBookings' => $this->myBookings($request),
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse|RedirectResponse
    {
        $unit = Unit::findOrFail($request->integer('console_id'));
        $start = Carbon::parse($request->input('start_time'));
        $end = $start->copy()->addHours($request->integer('duration_hours'));

        $booking = $this->bookings->createForCustomer(
            customer: $request->user(),
            unit: $unit,
            start: $start,
            end: $end,
            notes: $request->input('notes'),
        )->fresh('console');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking anda telah dikirim dan langsung kami kunci. Tunjukkan kode booking ke kasir saat datang.',
                'booking_code' => $booking->bookingCode(),
                'console_name' => $booking->console?->name,
                'start_time_label' => $booking->start_time->format('d M Y H:i'),
                'end_time_label' => $booking->end_time->format('d M Y H:i'),
                'total_price_label' => Money::format($booking->total_price),
                'status' => $booking->customerStatus(),
            ], 201);
        }

        return back()->with('success', 'Booking '.$booking->bookingCode().' berhasil dibuat.');
    }

    /**
     * Snapshot booking milik akun ini untuk polling halaman dashboard, supaya
     * status "Terisi" muncul tanpa perlu refresh manual.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'server_timestamp' => now()->getTimestampMs(),
            'bookings' => $this->myBookings($request),
        ]);
    }

    /**
     * Booking milik akun yang sedang login, terbaru lebih dulu.
     *
     * @return list<array<string, mixed>>
     */
    private function myBookings(Request $request): array
    {
        return Booking::query()
            ->forCustomer($request->user())
            ->with(['console', 'rentalSession'])
            ->latest('start_time')
            ->limit(10)
            ->get()
            ->map(fn (Booking $booking) => CustomerAvailability::booking($booking))
            ->all();
    }
}