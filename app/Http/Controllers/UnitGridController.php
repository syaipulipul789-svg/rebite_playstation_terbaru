<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitGridController extends Controller
{
    /**
     * Grid Monitoring Unit real-time. Halaman ini dilindungi middleware
     * `shift.active` sehingga kasir wajib menginput modal awal lebih dulu.
     */
    public function index(Request $request): View
    {
        $units = Unit::query()
            ->with(['runningSession' => fn ($q) => $q->with('user')])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $grouped = $units->groupBy('type');

        return view('units.index', [
            'units' => $units,
            'groupedUnits' => $grouped,
            'activeShift' => $request->attributes->get('activeShift'),
            'stats' => [
                'total' => $units->count(),
                'busy' => $units->where('status', UnitStatus::BUSY)->count(),
                'ready' => $units->where('status', UnitStatus::READY)->count(),
                'maintenance' => $units->where('status', UnitStatus::MAINTENANCE)->count(),
            ],
        ]);
    }
}
