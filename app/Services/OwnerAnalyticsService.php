<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\RentalSession;
use App\Models\SessionItem;
use App\Models\Shift;
use App\Models\Unit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
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
            'today_cash' => $this->revenueBetween($dayStart, $dayEnd, PaymentMethod::CASH),
            'today_qris' => $this->revenueBetween($dayStart, $dayEnd, PaymentMethod::QRIS),
            'today_sessions' => RentalSession::query()
                ->where('status', RentalSessionStatus::COMPLETED)
                ->whereBetween('end_time', [$dayStart, $dayEnd])
                ->count(),
            'today_orders' => Order::query()
                ->where('status', OrderStatus::COMPLETED)
                ->whereBetween('settled_at', [$dayStart, $dayEnd])
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

        $rows = $this->revenueRows($from->startOfDay(), $to->endOfDay())->groupBy('day');

        $labels = [];
        $totals = [];
        $cash = [];
        $qris = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            $bucket = $rows->get($cursor->toDateString(), collect());

            $cashTotal = Money::round($bucket->where('payment_method', 'CASH')->sum('total'));
            $qrisTotal = Money::round($bucket->where('payment_method', 'QRIS')->sum('total'));

            $labels[] = $cursor->format('d M');
            $cash[] = $cashTotal;
            $qris[] = $qrisTotal;
            $totals[] = Money::round($cashTotal + $qrisTotal);
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

        $rows = $this->revenueRows($from, $to)
            ->groupBy(fn (array $row) => $row['at']->startOfWeek()->toDateString());

        $labels = [];
        $totals = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor = $cursor->addWeek()) {
            $labels[] = $cursor->format('d M');
            $totals[] = Money::round($rows->get($cursor->toDateString(), collect())->sum('total'));
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * Pendapatan & jumlah sesi per unit dalam rentang tanggal.
     *
     * Pesanan barcode yang ditautkan ke unit ikut dihitung sebagai pendapatan
     * unit tersebut; pesanan tanpa unit (mis. hanya pesan makanan) tidak
     * masuk tabel ini karena tidak bisa diatribusikan ke konsol mana pun.
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
                'rental_revenue'
            )
            ->selectSub(
                Order::query()
                    ->selectRaw('COALESCE(SUM(total_price), 0)')
                    ->whereColumn('orders.unit_id', 'units.id')
                    ->where('status', OrderStatus::COMPLETED)
                    ->whereBetween('settled_at', [$from, $to]),
                'order_revenue'
            )
            ->selectSub(
                RentalSession::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('rental_sessions.unit_id', 'units.id')
                    ->where('status', RentalSessionStatus::COMPLETED)
                    ->whereBetween('end_time', [$from, $to]),
                'sessions_count'
            )
            ->selectSub(
                Order::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('orders.unit_id', 'units.id')
                    ->where('status', OrderStatus::COMPLETED)
                    ->whereBetween('settled_at', [$from, $to]),
                'orders_count'
            )
            ->get()
            ->each(function (Unit $unit) {
                $unit->revenue_total = Money::round((float) $unit->rental_revenue + (float) $unit->order_revenue);
            })
            // Diurutkan di PHP karena `revenue_total` adalah hasil penjumlahan
            // dua subquery, bukan kolom di database.
            ->sortByDesc('revenue_total')
            ->values();
    }

    /**
     * Pendapatan harian ringkas (label => total) untuk tabel laporan.
     */
    public function dailyBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->revenueRows($from, $to)->groupBy('day');

        $breakdown = [];

        for ($cursor = $from->copy()->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            $bucket = $rows->get($cursor->toDateString(), collect());
            $cash = Money::round($bucket->where('payment_method', 'CASH')->sum('total'));
            $qris = Money::round($bucket->where('payment_method', 'QRIS')->sum('total'));

            $breakdown[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('d M Y'),
                'sessions' => $bucket->where('is_order', false)->count(),
                'orders' => $bucket->where('is_order', true)->count(),
                'cash' => $cash,
                'qris' => $qris,
                'total' => Money::round($cash + $qris),
            ];
        }

        return $breakdown;
    }

    /**
     * Total pendapatan dalam rentang (sewa + F&B sesi + pesanan barcode),
     * opsional per metode pembayaran.
     */
    public function revenueBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?PaymentMethod $paymentMethod = null,
    ): float {
        $sessions = $this->completedSessionsQuery($from, $to)
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

        $orders = (float) $this->completedOrdersQuery($from, $to)
            ->when($paymentMethod, fn (Builder $q) => $q->where('payment_method', $paymentMethod))
            ->sum('total_price');

        return Money::round($sessions + $items + $orders);
    }

    /**
     * Baris pendapatan gabungan dari dua sumber — sesi rental (dihitung saat
     * `end_time`) dan pesanan barcode (dihitung saat `settled_at`, yaitu saat
     * kasir menerima pembayaran).
     *
     * Kedua sumber digabung di sini supaya grafik, tabel rekap, dan angka
     * "Total Pendapatan" tidak pernah berbeda satu sama lain.
     *
     * @return BaseCollection<int, array{day: string, at: CarbonImmutable, payment_method: ?string, total: float, is_order: bool}>
     */
    private function revenueRows(CarbonImmutable $from, CarbonImmutable $to): BaseCollection
    {
        $sessionRows = $this->completedSessionsQuery($from, $to)
            ->get([
                DB::raw('DATE(end_time) as day'),
                DB::raw('end_time'),
                'payment_method',
                DB::raw(self::SESSION_TOTAL.' as total'),
            ])
            ->map(fn (RentalSession $row) => [
                'day' => $row->getRawOriginal('day'),
                'at' => CarbonImmutable::parse($row->end_time),
                'payment_method' => $row->payment_method?->value,
                'total' => (float) $row->total,
                'is_order' => false,
            ]);

        $orderRows = $this->completedOrdersQuery($from, $to)
            ->get([
                DB::raw('DATE(settled_at) as day'),
                DB::raw('settled_at'),
                'payment_method',
                DB::raw('total_price as total'),
            ])
            ->map(fn (Order $row) => [
                'day' => $row->getRawOriginal('day'),
                'at' => CarbonImmutable::parse($row->settled_at),
                'payment_method' => $row->payment_method?->value,
                'total' => (float) $row->total,
                'is_order' => true,
            ]);

        return $sessionRows->concat($orderRows);
    }

    private function completedSessionsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return RentalSession::query()
            ->where('status', RentalSessionStatus::COMPLETED)
            ->whereBetween('end_time', [$from, $to]);
    }

    private function completedOrdersQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::COMPLETED)
            ->whereBetween('settled_at', [$from, $to]);
    }
}
