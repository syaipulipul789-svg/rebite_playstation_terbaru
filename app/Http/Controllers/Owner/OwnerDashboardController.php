<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Services\OwnerAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    public function __construct(private readonly OwnerAnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $summary = $this->analytics->summary();
        $daily = $this->analytics->revenueSeries(14);
        $weekly = $this->analytics->weeklySeries(8);

        $recentShifts = Shift::query()
            ->with('user')
            ->where('status', 'CLOSED')
            ->orderByDesc('end_time')
            ->limit(8)
            ->get();

        $recentLogs = AuditLog::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('owner.dashboard', [
            'summary' => $summary,
            'dailySeries' => $daily,
            'weeklySeries' => $weekly,
            'byUnit' => $this->analytics->revenueByUnit(
                CarbonImmutable::today()->subDays(29)->startOfDay(),
                CarbonImmutable::today()->endOfDay(),
            ),
            'recentShifts' => $recentShifts,
            'recentLogs' => $recentLogs,
        ]);
    }
}
