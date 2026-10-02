<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Http\Requests\CompleteBookingRequest;
use App\Http\Requests\SettleOrderRequest;
use App\Models\Booking;
use App\Models\Order;
use App\Models\RentalSession;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\ShiftService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class PosController extends Controller
{
    public function __construct(
        private readonly ShiftService $shifts,
        private readonly BookingService $bookings,
        private readonly OrderService $orders,
    ) {}

    /**
     * Halaman Kasir / POS Billing.
     *
     * Without shift_id -> tampilkan daftar tagihan aktif hari ini.
     * Dengan shift_id     -> fokus ke satu sesi untuk pencetakan nota.
     */
    public function index(Request $request): View
    {
        $shift = $this->shifts->activeShiftFor($request->user());

        if ($shift === null) {
            return redirect()->route('shift.start');
        }

        $activeSessions = RentalSession::query()
            ->with(['unit', 'user', 'items.product'])
            ->where('shift_id', $shift->id)
            ->where('status', RentalSessionStatus::RUNNING)
            ->orderBy('start_time')
            ->get();

        $recentSessions = RentalSession::query()
            ->with(['unit', 'items.product'])
            ->where('shift_id', $shift->id)
            ->where('status', RentalSessionStatus::COMPLETED)
            ->orderByDesc('end_time')
            ->limit(20)
            ->get();

        return view('pos.index', [
            'shift' => $shift,
            'activeSessions' => $activeSessions,
            'recentSessions' => $recentSessions,
            'runningRevenue' => Money::round($activeSessions->sum(
                fn ($s) => (float) $s->rental_fee + $s->itemsTotal()
            )),
            'closedRevenue' => Money::round($recentSessions->sum(
                fn ($s) => (float) $s->rental_fee + $s->itemsTotal()
            )),
            'pendingBookingsCount' => Booking::query()
                ->where('status', BookingStatus::PENDING)
                ->count(),
        ]);
    }

    /**
     * Daftar semua booking reservasi online yang masuk, untuk disetujui
     * (confirm) atau dibatalkan (cancel) oleh kasir.
     */
    public function bookings(Request $request): View
    {
        $shift = $this->shifts->activeShiftFor($request->user());

        if ($shift === null) {
            return redirect()->route('shift.start');
        }

        $bookings = Booking::query()
            ->with('console')
            ->orderByDesc('start_time')
            ->get();

        return view('pos.bookings', [
            'shift' => $shift,
            'bookings' => $bookings,
            'pendingCount' => $bookings
                ->where('status', BookingStatus::PENDING)
                ->count(),
        ]);
    }

    public function confirmBooking(Booking $booking): RedirectResponse
    {
        try {
            $confirmed = $this->bookings->confirm($booking, $this->currentUser());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "Booking {$confirmed->bookingCode()} untuk {$confirmed->console->name} dikonfirmasi.";

        // Kalau jam slotnya sudah berjalan, unit langsung jadi Terisi dan
        // monitor mulai menghitung mundur. Booking untuk slot nanti akan
        // dipromote otomatis oleh command `bookings:promote`.
        return back()->with('success', $confirmed->rental_session_id
            ? $message.' Unit ditandai Terisi dan mulai berjalan.'
            : $message.' Unit ditandai Terisi otomatis saat jam mulai.');
    }

    public function cancelBooking(Booking $booking): RedirectResponse
    {
        try {
            $cancelled = $this->bookings->cancel($booking);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Booking {$cancelled->bookingCode()} untuk {$cancelled->console->name} dibatalkan.");
    }

    public function completeBooking(CompleteBookingRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $completed = $this->bookings->complete(
                $booking,
                $request->paymentMethod(),
                $request->validated('note'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Booking {$completed->bookingCode()} untuk {$completed->console->name} selesai. Unit kembali Ready.");
    }

    /**
     * Pesanan menu hasil pindai barcode pelanggan yang menunggu dibayar.
     */
    public function orders(Request $request): View
    {
        $shift = $this->shifts->activeShiftFor($request->user());

        if ($shift === null) {
            return redirect()->route('shift.start');
        }

        $filter = $request->string('status')->toString() ?: 'awaiting';

        $query = Order::query()
            // `rentalSession` dibutuhkan supaya kasir tahu order QR meja
            // dibayar lewat checkout sewa, bukan tombol terima bayar di sini.
            ->with(['unit', 'items.product', 'booking', 'user', 'rentalSession.unit'])
            ->orderByDesc('created_at');

        match ($filter) {
            'paid' => $query->completed(),
            'all' => $query,
            default => $query->awaitingPayment(),
        };

        return view('pos.orders', [
            'shift' => $shift,
            'orders' => $query->limit(50)->get(),
            'filter' => $filter,
            'awaitingCount' => Order::query()->awaitingPayment()->count(),
            'draftCount' => Order::query()->where('status', OrderStatus::PENDING)->count(),
        ]);
    }

    public function settleOrder(SettleOrderRequest $request, Order $order): RedirectResponse
    {
        try {
            $settled = $this->orders->settle(
                order: $order,
                cashier: $request->user(),
                paymentMethod: PaymentMethod::from($request->string('payment_method')->toString()),
                note: $request->input('note'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pesanan {$settled->code} dibayar — masuk rekap shift.");
    }

    /**
     * Tandai pesanan QR meja sudah diantar ke pelanggan.
     *
     * Hanya perubahan status informatif: pembayaran tetap terjadi satu kali
     * saat rental di-checkout, jadi kasir tidak perlu membayar per pesanan.
     */
    public function serveOrder(Order $order): RedirectResponse
    {
        try {
            $served = $this->orders->markServed($order, $this->currentUser());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pesanan {$served->code} ditandai sudah diantar.");
    }

    public function cancelOrder(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $cancelled = $this->orders->cancel($order, $validated['reason'] ?? null, $this->currentUser());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pesanan {$cancelled->code} dibatalkan dan stok dikembalikan.");
    }

    public function receipt(Request $request, int $session): View
    {
        $record = RentalSession::query()
            ->with(['unit', 'items.product', 'user', 'shift'])
            // Pesanan QR ikut dirinci karena dibayar satu transaksi dengan sewa.
            ->with(['billableOrders.items.product'])
            ->findOrFail($session);

        return view('pos.receipt', [
            'session' => $record,
        ]);
    }

    /**
     * Halaman POS dijaga middleware auth + shift aktif, jadi user selalu ada.
     */
    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
