@extends('layouts.app')

@section('title', 'Laporan Keuangan')
@section('page-title', 'Laporan Keuangan')
@section('page-subtitle', 'Periode ' . $from->format('d M Y') . ' – ' . $to->format('d M Y'))

@section('content')
    <div x-data="chart" x-init="mountAll()" class="space-y-6">

        {{-- ================= FILTER PERIODE ================= --}}
        {{-- Filter punya scope Alpine sendiri supaya `preset` benar-benar
             terdefinisi; sebelumnya x-model/x-show mengacu ke variabel yang
             tidak ada sehingga kolom tanggal kustom tidak pernah muncul. --}}
        <form method="GET"
              action="{{ route('owner.reports') }}"
              x-data="{ preset: @js(request('range', 'today')) }"
              class="card flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[190px] flex-1">
                <label for="range" class="label">Periode</label>
                <select id="range" name="range" x-model="preset" class="input">
                    @foreach ([
                        'today' => 'Hari Ini',
                        'week' => 'Minggu Ini',
                        'month' => 'Bulan Ini',
                        'quarter' => '3 Bulan Terakhir',
                        'year' => 'Tahun Ini',
                        'custom' => 'Rentang Kustom',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($value === request('range', 'today'))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="preset === 'custom'" class="min-w-[150px] flex-1">
                <label for="from" class="label">Dari Tanggal</label>
                <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="input">
            </div>

            <div x-show="preset === 'custom'" class="min-w-[150px] flex-1">
                <label for="to" class="label">Sampai Tanggal</label>
                <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="input">
            </div>

            <button type="submit" class="btn-primary">
                <x-icon name="search" class="h-4 w-4" />
                Terapkan
            </button>
        </form>

        {{-- ================= TOTAL PERIODE ================= --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="Total Pendapatan"
                :value="\App\Support\Money::format($range['revenue'])"
                :hint="$range['shift_count'] . ' shift tertutup'"
                icon="circle-dollar-sign"
                tone="brand"
            />

            <x-stat-card
                label="Komposisi Tunai / QRIS"
                :value="\App\Support\Money::format($range['cash'])"
                :hint="'QRIS ' . \App\Support\Money::format($range['qris'])"
                icon="banknote"
                tone="emerald"
            />

            <x-stat-card
                label="Total Modal Awal"
                :value="\App\Support\Money::format($range['starting_cash'])"
                hint="Dana kas yang ditarik kasir saat mulai shift"
                icon="wallet"
                tone="sky"
            />

            <x-stat-card
                label="Akumulasi Selisih Kas"
                :value="\App\Support\Money::formatSigned($range['discrepancy'])"
                :hint="$range['discrepancy_count'] . ' shift berselisih'"
                :tone="$range['discrepancy'] == 0 ? 'emerald' : 'amber'"
                icon="arrow-left-right"
            />
        </div>

        {{-- ================= GRAFIK ================= --}}
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="card p-5 xl:col-span-2">
                <h2 class="mb-4 text-sm font-bold text-white">Pendapatan Harian</h2>
                <div class="h-72">
                    <canvas id="chart-revenue-daily" data-series="{{ json_encode([
                        'labels' => array_column($daily, 'label'),
                        'cash' => array_column($daily, 'cash'),
                        'qris' => array_column($daily, 'qris'),
                    ]) }}"></canvas>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-4 text-sm font-bold text-white">10 Unit Teratas</h2>
                <div class="h-72">
                    <canvas id="chart-revenue-unit" data-series="{{ json_encode([
                        'labels' => $byUnit->take(10)->pluck('name'),
                        'totals' => $byUnit->take(10)->map(fn ($u) => (float) $u->revenue_total),
                    ]) }}"></canvas>
                </div>
            </div>
        </div>

        {{-- ================= REKAP HARIAN ================= --}}
        <div class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-white/5 px-5 py-4">
                <h2 class="text-sm font-bold text-white">Rekap Harian</h2>
                <span class="badge bg-white/5 text-slate-400">{{ count($daily) }} hari</span>
            </header>

            <div class="max-h-[28rem] overflow-y-auto">
                <table class="table-compact">
                    <thead class="sticky top-0 z-10 bg-ink-850">
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-center">Sesi</th>
                            <th class="text-right">Tunai</th>
                            <th class="text-right">QRIS</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($daily as $row)
                            <tr>
                                <td class="text-xs font-semibold text-slate-300">{{ $row['label'] }}</td>
                                <td class="tabular text-center text-xs text-slate-500">{{ $row['sessions'] }}</td>
                                <td class="tabular text-right text-xs text-slate-400">{{ \App\Support\Money::format($row['cash'], false) }}</td>
                                <td class="tabular text-right text-xs text-slate-400">{{ \App\Support\Money::format($row['qris'], false) }}</td>
                                <td class="tabular text-right text-xs font-bold text-white">{{ \App\Support\Money::format($row['total'], false) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-xs text-slate-600">
                                    Tidak ada transaksi pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ================= TABEL SHIFT ================= --}}
        <div class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-white/5 px-5 py-4">
                <h2 class="text-sm font-bold text-white">Rekap Rekonsiliasi Shift</h2>
                <button type="button" x-data x-on:click="window.print()" class="btn-subtle text-xs">
                    <x-icon name="printer" class="h-3.5 w-3.5" />
                    Cetak
                </button>
            </header>

            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Kasir</th>
                            <th>Waktu</th>
                            <th class="text-right">Modal Awal</th>
                            <th class="text-right">Tunai</th>
                            <th class="text-right">QRIS</th>
                            <th class="text-right">Ekspektasi</th>
                            <th class="text-right">Fisik</th>
                            <th class="text-right">Selisih</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr>
                                <td class="text-xs font-semibold text-slate-200">{{ $shift->user->name }}</td>

                                <td class="tabular text-xs text-slate-500">
                                    {{ $shift->start_time->format('d M H:i') }}<br>
                                    <span class="text-slate-600">→ {{ $shift->end_time?->format('H:i') }}</span>
                                </td>

                                <td class="tabular text-right text-xs text-slate-400">{{ \App\Support\Money::format($shift->starting_cash, false) }}</td>
                                <td class="tabular text-right text-xs text-emerald-300/80">{{ \App\Support\Money::format($shift->system_cash_revenue, false) }}</td>
                                <td class="tabular text-right text-xs text-sky-300/80">{{ \App\Support\Money::format($shift->system_qris_revenue, false) }}</td>
                                <td class="tabular text-right text-xs text-slate-300">{{ \App\Support\Money::format($shift->expectedCash(), false) }}</td>
                                <td class="tabular text-right text-xs text-slate-300">{{ \App\Support\Money::format($shift->actual_physical_cash, false) }}</td>

                                <td class="text-right">
                                    <x-badge :variant="$shift->hasDiscrepancy() ? 'amber' : 'emerald'">
                                        {{ \App\Support\Money::formatSigned($shift->discrepancy) }}
                                    </x-badge>
                                </td>

                                <td class="max-w-[16rem] text-xs text-slate-500">
                                    <span class="line-clamp-2">{{ $shift->note ?? '—' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-10 text-center text-xs text-slate-600">
                                    Belum ada shift tertutup pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($shifts->hasPages())
                <div class="border-t border-white/5 px-5 py-3">
                    {{ $shifts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
