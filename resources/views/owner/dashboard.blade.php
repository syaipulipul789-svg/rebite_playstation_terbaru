@extends('layouts.app')

@section('title', 'Dashboard Owner')
@section('page-title', 'Dashboard Owner')
@section('page-subtitle', 'Ringkasan operasional & keuangan ' . $summary['date']->format('d F Y'))

@section('content')
    <div x-data="chart" x-init="mountAll()" class="space-y-6">

        {{-- ================= STAT CARDS ================= --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="Pendapatan Hari Ini"
                :value="\App\Support\Money::format($summary['today_revenue'])"
                :hint="$summary['today_sessions'] . ' sesi & ' . $summary['today_orders'] . ' pesanan selesai'"
                icon="circle-dollar-sign"
                tone="brand"
            />

            <x-stat-card
                label="Tunai / QRIS Hari Ini"
                :value="\App\Support\Money::format($summary['today_cash'])"
                :hint="'QRIS ' . \App\Support\Money::format($summary['today_qris'])"
                icon="banknote"
                tone="emerald"
            />

            <x-stat-card
                label="Tingkat Keterisian"
                :value="\App\Support\Money::formatPercent($summary['occupancy_rate'])"
                :hint="$summary['units_busy'] . ' dari ' . $summary['units_total'] . ' unit terpakai'"
                icon="gauge"
                tone="sky"
            />

            <x-stat-card
                label="Selisih Kas Bulan Ini"
                :value="\App\Support\Money::formatSigned($summary['monthly_discrepancy'])"
                :hint="$summary['shift_with_discrepancy'] . ' dari ' . $summary['monthly_shift_count'] . ' shift'"
                :tone="$summary['monthly_discrepancy'] == 0 ? 'emerald' : 'amber'"
                icon="arrow-left-right"
            />
        </div>

        {{-- ================= GRAFIK ================= --}}
        <div class="grid gap-4 xl:grid-cols-3">

            {{-- Pendapatan 14 hari --}}
            <div class="card p-5 xl:col-span-2">
                <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-white">Tren Pendapatan 14 Hari</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Pemisahan metode pembayaran tunai dan QRIS</p>
                    </div>

                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Tertinggi</p>
                        <p class="tabular text-sm font-extrabold text-emerald-300">
                            {{ \App\Support\Money::format($dailySeries['peak']) }}
                        </p>
                    </div>
                </header>

                <div class="h-72">
                    <canvas id="chart-revenue-daily" data-series="{{ json_encode($dailySeries) }}"></canvas>
                </div>
            </div>

            {{-- Komposisi per unit --}}
            <div class="card p-5">
                <header class="mb-4">
                    <h2 class="text-sm font-bold text-white">Komposisi per Unit</h2>
                    <p class="mt-0.5 text-xs text-slate-500">7 unit dengan pendapatan terbesar</p>
                </header>

                <div class="h-72">
                    <canvas id="chart-revenue-unit" data-series="{{ json_encode([
                        'labels' => $byUnit->take(7)->pluck('name'),
                        'totals' => $byUnit->take(7)->map(fn ($u) => (float) $u->revenue_total),
                    ]) }}"></canvas>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">

            {{-- Status unit --}}
            <div class="card p-5">
                <h2 class="text-sm font-bold text-white">Status Unit</h2>

                <div class="mt-4 space-y-3">
                    @foreach ([
                        ['label' => 'Siap Dipakai', 'value' => $summary['units_ready'], 'total' => $summary['units_total'], 'color' => 'bg-emerald-500'],
                        ['label' => 'Sedang Disewa', 'value' => $summary['units_busy'], 'total' => $summary['units_total'], 'color' => 'bg-rose-500'],
                        ['label' => 'Servis', 'value' => $summary['units_maintenance'], 'total' => $summary['units_total'], 'color' => 'bg-amber-500'],
                    ] as $row)
                        @php($percent = $row['total'] > 0 ? round($row['value'] / $row['total'] * 100) : 0)

                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-300">{{ $row['label'] }}</span>
                                <span class="tabular text-slate-500">{{ $row['value'] }} / {{ $row['total'] }}</span>
                            </div>

                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/5">
                                <div class="h-full rounded-full {{ $row['color'] }}" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 space-y-2 border-t border-white/5 pt-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400">Shift sedang berjalan</span>
                        <x-badge :variant="$summary['open_shift_count'] > 0 ? 'emerald' : 'slate'" dot>
                            {{ $summary['open_shift_count'] }} kasir
                        </x-badge>
                    </div>

                    @forelse ($summary['open_shifts'] as $openShift)
                        <div class="flex items-center justify-between text-xs">
                            <span class="truncate text-slate-500">{{ $openShift->user->name }}</span>
                            <span class="tabular shrink-0 text-slate-500">
                                sejak {{ $openShift->start_time->format('H:i') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-600">Tidak ada kasir yang sedang shift.</p>
                    @endforelse
                </div>
            </div>

            {{-- Pendapatan 8 minggu --}}
            <div class="card p-5">
                <header class="mb-4">
                    <h2 class="text-sm font-bold text-white">Pendapatan Mingguan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">8 minggu terakhir</p>
                </header>

                <div class="h-56">
                    <canvas id="chart-revenue-weekly" data-series="{{ json_encode($weeklySeries) }}"></canvas>
                </div>
            </div>

            {{-- Stok menipis --}}
            <div class="card p-5">
                <header class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white">Stok Menipis</h2>
                    <a href="{{ route('owner.products.index') }}" class="text-xs font-semibold text-brand-300 hover:text-brand-200">
                        Kelola
                    </a>
                </header>

                <div class="space-y-2">
                    @forelse ($summary['low_stock'] as $product)
                        <div class="flex items-center justify-between rounded-lg bg-ink-900 px-3 py-2">
                            <span class="truncate text-xs font-semibold text-slate-300">{{ $product->name }}</span>
                            <x-badge :variant="$product->stock <= 0 ? 'rose' : 'amber'" dot>
                                sisa {{ $product->stock }}
                            </x-badge>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-slate-600">Semua stok aman.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ================= TABEL SHIFT TERAKHIR ================= --}}
        <div class="grid gap-4 xl:grid-cols-2">

            <div class="card overflow-hidden">
                <header class="flex items-center justify-between border-b border-white/5 px-5 py-4">
                    <h2 class="text-sm font-bold text-white">Rekap Shift Terakhir</h2>
                    <a href="{{ route('owner.reports') }}" class="text-xs font-semibold text-brand-300 hover:text-brand-200">
                        Lihat laporan
                    </a>
                </header>

                <div class="overflow-x-auto">
                    <table class="table-compact">
                        <thead>
                            <tr>
                                <th>Kasir</th>
                                <th class="text-right">Tunai</th>
                                <th class="text-right">QRIS</th>
                                <th class="text-right">Selisih</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recentShifts as $shift)
                                <tr>
                                    <td>
                                        <p class="text-xs font-semibold text-slate-200">{{ $shift->user->name }}</p>
                                        <p class="tabular text-[10px] text-slate-500">
                                            {{ $shift->end_time?->format('d M, H:i') }}
                                        </p>
                                    </td>

                                    <td class="tabular text-right text-xs text-slate-300">
                                        {{ \App\Support\Money::format($shift->system_cash_revenue, false) }}
                                    </td>

                                    <td class="tabular text-right text-xs text-slate-300">
                                        {{ \App\Support\Money::format($shift->system_qris_revenue, false) }}
                                    </td>

                                    <td class="text-right">
                                        <x-badge :variant="$shift->hasDiscrepancy() ? 'amber' : 'emerald'">
                                            {{ \App\Support\Money::formatSigned($shift->discrepancy) }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-xs text-slate-600">
                                        Belum ada shift yang ditutup.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Audit feed --}}
            <div class="card overflow-hidden">
                <header class="flex items-center justify-between border-b border-white/5 px-5 py-4">
                    <h2 class="text-sm font-bold text-white">Aktivitas Terakhir</h2>
                    <a href="{{ route('owner.audit-log') }}" class="text-xs font-semibold text-brand-300 hover:text-brand-200">
                        Audit log
                    </a>
                </header>

                <ul class="divide-y divide-white/5">
                    @forelse ($recentLogs as $log)
                        <li class="flex items-start gap-3 px-5 py-3">
                            <span @class([
                                'mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg',
                                'bg-emerald-500/15 text-emerald-300' => str_contains($log->event, 'COMPLETED'),
                                'bg-amber-500/15 text-amber-300' => str_contains($log->event, 'CANCELLED'),
                                'bg-sky-500/15 text-sky-300' => str_contains($log->event, 'STARTED'),
                                'bg-white/5 text-slate-400' => true,
                            ])>
                                <x-icon name="activity" class="h-3.5 w-3.5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-slate-200">{{ $log->description }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">
                                    {{ $log->user?->name ?? 'Sistem' }} ·
                                    <span class="tabular">{{ $log->created_at->diffForHumans() }}</span>
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-xs text-slate-600">Belum ada aktivitas.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
