<?php

namespace App\Services;

use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Models\Product;
use App\Models\RentalSession;
use App\Models\SessionItem;
use App\Models\Shift;
use App\Models\Unit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class OwnerAnalyticsService
{
    /**
     * Subquery total tagihan sesi (sewa + F&B) agar bisa dipakai ulang di agregasi.
     */
    private const SESSION_TOTAL = '(rental_fee + COALESCE((SELECT SUM(subtotal) FROM session_items WHERE session_items.rental_session_id = rental_sessions.id), 0))';

    /**
     * Stat cards utama untuk dashboard Owner.
     */
    public function summary(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::today();
        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();
        $monthStart = $date->startOfMonth();

        $openShifts = Shift::query()
            ->where('status', ShiftStatus::OPEN)
            ->with('user')
            ->get();

        $monthlyDiscrepancy = (float) Shift::query()
            ->where('status', ShiftStatus::CLOSED)
            ->where('end_time', '>=', $monthStart)
            ->sum('discrepancy');

        return [
            'date' => $date,
            'today_revenue' => $this->revenueBetween($dayStart, $dayEnd),
            'today_cash' => $this->revenueBetween($dayStart, $dayEnd, 'CASH'),
            'today_qris' => $this->revenueBetween($dayStart, $dayEnd, 'QRIS'),
            'today_sessions' => RentalSession::query()
                ->where('status', RentalSessionStatus::COMPLETED)
                ->whereBetween('end_time', [$dayStart, $dayEnd])
                ->count(),
            'occupancy_rate' => $this->occupancyRate($date),
            'units_total' => Unit::query()->count(),
            'units_busy' => Unit::query()->where('status', UnitStatus::BUSY)->count(),
            'units_ready' => Unit::query()->where('status', UnitStatus::READY)->count(),
            'units_maintenance' => Unit::query()->where('status', UnitStatus::MAINTENANCE)->count(),
            'monthly_discrepancy' => Money::round($monthlyDiscrepancy),
            'shift_with_discrepancy' => Shift::query()
                ->where('status', ShiftStatus::CLOSED)
                ->where('end_time', '>=', $monthStart)
                ->where('discrepancy', '!=', 0)
                ->count(),
            'monthly_shift_count' => Shift::query()
                ->where('status', ShiftStatus::CLOSED)
                ->where('end_time', '>=', $monthStart)
                ->count(),
            'open_shift_count' => $openShifts->count(),
            'open_shifts' => $openShifts,
            'low_stock' => Product::query()
                ->active()
                ->get()
                ->filter(fn ($p) => $p->isLowStock())
                ->values(),
        ];
    }

    /**
     * Tingkat keterisian unit = durasi sewa terpakai / durasi unit tersedia.
     * Unit berstatus MAINTENANCE dikeluarkan dari pembagi.
     */
    public function occupancyRate(?CarbonImmutable $date = null): float
    {
        $date ??= CarbonImmutable::today();
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $totalUnits = Unit::query()
            ->where('status', '!=', UnitStatus::MAINTENANCE)
            ->count();

        if ($totalUnits === 0) {
            return 0.0;
        }

        $usedMinutes = (int) RentalSession::query()
            ->where('status', RentalSessionStatus::COMPLETED)
            ->whereBetween('start_time', [$start, $end])
            ->sum('duration_minutes');

        $availableMinutes = $totalUnits * $start->diffInMinutes($end);

        if ($availableMinutes <= 0) {
            return 0.0;
        }

        return round(min(100, ($usedMinutes / $availableMinutes) * 100), 2);
    }

    /**
     * Deret waktu harian untuk Chart.js, dipisah per metode pembayaran.
     */
    public function revenueSeries(int $days = 14, ?CarbonImmutable $from = null): array
    {
        $from ??= CarbonImmutable::today()->subDays($days - 1);
        $to = CarbonImmutable::today();

        $rows = $this->completedSessionsQuery($from->startOfDay(), $to->endOfDay())
            ->get([DB::raw('DATE(end_time) as day'), 'payment_method', DB::raw(self::SESSION_TOTAL.' as total')])
            ->groupBy('day');

        $labels = [];
        $totals = [];
        $cash = [];
        $qris = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            $bucket = $rows->get($cursor->toDateString(), collect());

            $cashTotal = (float) $bucket->where('payment_method', 'CASH')->sum('total');
            $qrisTotal = (float) $bucket->where('payment_method', 'QRIS')->sum('total');

            $labels[] = $cursor->format('d M');
            $cash[] = $cashTotal;
            $qris[] = $qrisTotal;
            $totals[] = $cashTotal + $qrisTotal;
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
            'cash' => $cash,
            'qris' => $qris,
            'peak' => $totals === [] ? 0.0 : max($totals),
        ];
    }

    /**
     * Rekap per minggu (Senin-Minggu) untuk 8 minggu terakhir.
     */
    public function weeklySeries(int $weeks = 8): array
    {
        $from = CarbonImmutable::today()->subWeeks($weeks - 1)->startOfWeek();
        $to = CarbonImmutable::today()->endOfWeek();

        $rows = $this->completedSessionsQuery($from, $to)
            ->get([DB::raw('end_time'), DB::raw(self::SESSION_TOTAL.' as total')])
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->end_time)->startOfWeek()->toDateString());

        $labels = [];
        $totals = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor = $cursor->addWeek()) {
            $labels[] = $cursor->format('d M');
            $totals[] = (float) $rows->get($cursor->toDateString(), collect())->sum('total');
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * Pendapatan & jumlah sesi per unit dalam rentang tanggal.
     */
    public function revenueByUnit(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Unit::query()
            ->select('units.*')
            ->selectSub(
                RentalSession::query()
                    ->selectRaw('COALESCE(SUM('.self::SESSION_TOTAL.'), 0)')
                    ->whereColumn('rental_sessions.unit_id', 'units.id')
                    ->where('status', RentalSessionStatus::COMPLETED)
                    ->whereBetween('end_time', [$from, $to]),
                'revenue_total'
            )
            ->selectSub(
                RentalSession::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('rental_sessions.unit_id', 'units.id')
                    ->where('status', RentalSessionStatus::COMPLETED)
                    ->whereBetween('end_time', [$from, $to]),
                'sessions_count'
            )
            ->orderByDesc('revenue_total')
            ->get();
    }

    /**
     * Pendapatan harian ringkas (label => total) untuk tabel laporan.
     */
    public function dailyBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->completedSessionsQuery($from, $to)
            ->get([DB::raw('DATE(end_time) as day'), 'payment_method', DB::raw(self::SESSION_TOTAL.' as total')])
            ->groupBy('day');

        $breakdown = [];

        for ($cursor = $from->copy()->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            $bucket = $rows->get($cursor->toDateString(), collect());
            $cash = (float) $bucket->where('payment_method', 'CASH')->sum('total');
            $qris = (float) $bucket->where('payment_method', 'QRIS')->sum('total');

            $breakdown[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('d M Y'),
                'sessions' => $bucket->count(),
                'cash' => $cash,
                'qris' => $qris,
                'total' => $cash + $qris,
            ];
        }

        return $breakdown;
    }

    /**
     * Total pendapatan (sewa + F&B) dalam rentang, opsional per metode pembayaran.
     */
    public function revenueBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $paymentMethod = null,
    ): float {
        $fees = (float) $this->completedSessionsQuery($from, $to)
            ->when($paymentMethod, fn (Builder $q) => $q->where('payment_method', $paymentMethod))
            ->sum('rental_fee');

        $items = (float) SessionItem::query()
            ->whereIn(
                'rental_session_id',
                $this->completedSessionsQuery($from, $to)
                    ->when($paymentMethod, fn (Builder $q) => $q->where('payment_method', $paymentMethod))
                    ->select('rental_sessions.id')
            )
            ->sum('subtotal');

        return Money::round($fees + $items);
    }

    private function completedSessionsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return RentalSession::query()
            ->where('status', RentalSessionStatus::COMPLETED)
            ->whereBetween('end_time', [$from, $to]);
    }
}
