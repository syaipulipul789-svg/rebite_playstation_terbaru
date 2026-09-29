<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndShiftRequest;
use App\Http\Requests\StartShiftRequest;
use App\Services\ShiftService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

    /**
     * Halaman Gatekeeper Shift — input Modal Awal Kas.
     */
    public function start(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isOwner()) {
            return redirect()->route('owner.dashboard');
        }

        $activeShift = $this->shifts->activeShiftFor($user);

        if ($activeShift !== null) {
            return redirect()->route('units.index')
                ->with('info', 'Shift Anda sedang berjalan.');
        }

        return view('shift.start', [
            'lastShift' => $user->shifts()->closed()->latest('end_time')->first(),
        ]);
    }

    public function store(StartShiftRequest $request): RedirectResponse
    {
        try {
            $shift = $this->shifts->start($request->user(), (float) $request->input('starting_cash'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('units.index')
            ->with('success', 'Shift dimulai dengan modal awal '.Money::format($shift->starting_cash).'.');
    }

    /**
     * Halaman Rekonsiliasi Akhir Shift.
     */
    public function end(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $shift = $this->shifts->activeShiftFor($user);

        if ($shift === null) {
            return redirect()->route('shift.start')
                ->with('warning', 'Tidak ada shift aktif untuk direkonsiliasi.');
        }

        $preview = $this->shifts->previewReconciliation($shift);

        return view('shift.end', [
            'shift' => $shift,
            'preview' => $preview,
        ]);
    }

    public function close(EndShiftRequest $request): RedirectResponse
    {
        $shift = $this->shifts->activeShiftFor($request->user());

        if ($shift === null) {
            return redirect()->route('shift.start')
                ->with('error', 'Shift sudah tertutup atau tidak ditemukan.');
        }

        try {
            $closed = $this->shifts->close(
                $shift,
                (float) $request->input('actual_physical_cash'),
                $request->input('note'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('shift.summary', $closed)
            ->with('success', 'Shift ditutup. Rekap terkirim ke audit log Owner.');
    }

    /**
     * Ringkasan hasil rekonsiliasi setelah shift ditutup.
     */
    public function summary(Request $request, int $shift): View|RedirectResponse
    {
        $record = $request->user()->shifts()->findOrFail($shift);

        return view('shift.summary', [
            'shift' => $record,
        ]);
    }
}
