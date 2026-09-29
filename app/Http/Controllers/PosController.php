<?php

namespace App\Http\Controllers;

use App\Enums\RentalSessionStatus;
use App\Models\RentalSession;
use App\Services\ShiftService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

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
        ]);
    }

    public function receipt(Request $request, int $session): View
    {
        $record = RentalSession::query()
            ->with(['unit', 'items.product', 'user', 'shift'])
            ->findOrFail($session);

        return view('pos.receipt', [
            'session' => $record,
        ]);
    }
}
