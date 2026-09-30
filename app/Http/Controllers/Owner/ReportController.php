<?php

namespace App\Http\Controllers\Owner;

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;
use App\Services\OwnerAnalyticsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ReportController extends Controller
{
    public function __construct(private readonly OwnerAnalyticsService $analytics) {}

    /**
     * Laporan Keuangan: filter periode + audit log rekonsiliasi shift.
     */
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);

        $base = Shift::query()
            ->where('status', ShiftStatus::CLOSED)
            ->whereBetween('end_time', [$from, $to]);

        // Agregat dihitung dari SELURUH shift pada periode, bukan hanya baris
        // yang kebetulan muncul di halaman paginator saat ini.
        $totals = (clone $base)
            ->selectRaw('COALESCE(SUM(system_cash_revenue), 0) as cash')
            ->selectRaw('COALESCE(SUM(system_qris_revenue), 0) as qris')
            ->selectRaw('COALESCE(SUM(starting_cash), 0) as starting_cash')
            ->selectRaw('COALESCE(SUM(discrepancy), 0) as discrepancy')
            ->selectRaw('COUNT(*) as shift_count')
            ->selectRaw('SUM(CASE WHEN discrepancy != 0 THEN 1 ELSE 0 END) as discrepancy_count')
            ->first();

        $shifts = (clone $base)
            ->with('user')
            ->orderByDesc('end_time')
            ->paginate(15)
            ->withQueryString();

        return view('owner.reports', [
            'from' => $from,
            'to' => $to,
            'shifts' => $shifts,
            'daily' => $this->analytics->dailyBreakdown($from, $to),
            'byUnit' => $this->analytics->revenueByUnit($from, $to),
            'range' => [
                'revenue' => $this->analytics->revenueBetween($from, $to),
                'cash' => $this->analytics->revenueBetween($from, $to, PaymentMethod::CASH),
                'qris' => $this->analytics->revenueBetween($from, $to, PaymentMethod::QRIS),
                'starting_cash' => (float) $totals->starting_cash,
                'shift_cash' => (float) $totals->cash,
                'shift_qris' => (float) $totals->qris,
                'discrepancy' => Money::round((float) $totals->discrepancy),
                'discrepancy_count' => (int) ($totals->discrepancy_count ?? 0),
                'shift_count' => (int) $totals->shift_count,
            ],
        ]);
    }

    /**
     * Audit Log Rekonsiliasi Shift lengkap dengan filter event.
     */
    public function auditLog(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['user', 'shift'])
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')->toString()))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('owner.audit-log', [
            'logs' => $logs,
            'filters' => $request->only(['event', 'user_id']),
            'eventOptions' => [
                AuditLog::EVENT_SHIFT_STARTED,
                AuditLog::EVENT_SHIFT_CLOSED,
                AuditLog::EVENT_SESSION_STARTED,
                AuditLog::EVENT_SESSION_EXTENDED,
                AuditLog::EVENT_SESSION_COMPLETED,
                AuditLog::EVENT_SESSION_CANCELLED,
                AuditLog::EVENT_MASTER_UPDATED,
            ],
            'cashiers' => User::query()
                ->where('role', 'KASIR')
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request): array
    {
        $preset = $request->input('range');

        // `?range[]=x` membuat input berupa array; harus dibuang, bukan
        // diteruskan ke string() yang akan melempar TypeError -> 500.
        $preset = is_string($preset) ? $preset : 'today';

        $to = CarbonImmutable::today()->endOfDay();
        $from = match ($preset) {
            'week' => CarbonImmutable::today()->startOfWeek(),
            'month' => CarbonImmutable::today()->startOfMonth(),
            'quarter' => CarbonImmutable::today()->subMonths(2)->startOfMonth(),
            'year' => CarbonImmutable::today()->startOfYear(),
            'custom' => $this->parseDate($request->input('from'), $to->startOfDay()),
            default => CarbonImmutable::today()->startOfDay(),
        };

        if ($preset === 'custom' && $request->filled('to')) {
            $to = $this->parseDate($request->input('to'), $to);
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Parse tanggal dari input user dengan fallback aman.
     *
     * Tanpa guard ini, `CarbonImmutable::parse('bukan-tanggal')` melempar
     * InvalidFormatException dan halaman laporan balas 500 untuk input rusak.
     */
    private function parseDate(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->startOfDay();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
