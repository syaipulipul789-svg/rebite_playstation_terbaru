@extends('layouts.app')

@use('App\Enums\PaymentMethod')

@section('title', 'Kasir / Billing')
@section('page-title', 'Kasir')
@section('page-subtitle', 'Shift ' . $shift->start_time->format('d F Y H:i') . ' — ' . $shift->durationInMinutes() . ' menit berjalan')

@section('content')
    <div class="space-y-6">

        {{-- ================= RINGKASAN SHIFT ================= --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="Modal Awal"
                :value="\App\Support\Money::format($shift->starting_cash)"
                :hint="'Mulai ' . $shift->start_time->format('H:i')"
                icon="wallet"
                tone="brand"
            />

            <x-stat-card
                label="Sesi Berjalan"
                :value="$activeSessions->count() . ' unit'"
                :hint="\App\Support\Money::format($runningRevenue) . ' belum dibayar'"
                icon="clock"
                tone="rose"
            />

            <x-stat-card
                label="Sudah Dibayar"
                :value="\App\Support\Money::format($closedRevenue)"
                :hint="$recentSessions->count() . ' sesi selesai'"
                icon="banknote"
                tone="emerald"
            />

            <x-stat-card
                label="Ekspektasi Kas"
                :value="\App\Support\Money::format($shift->system_cash_revenue)"
                hint="Modal awal + pendapatan tunai"
                icon="arrow-up-right"
                tone="sky"
            />
        </div>

        {{-- ================= SESI SEDANG BERJALAN ================= --}}
        <div class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4">
                <div>
                    <h2 class="text-sm font-bold text-white">Sesi Sedang Berjalan</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Kelola tagihan dan penyelesaian sewa dari grid unit</p>
                </div>

                <a href="{{ route('units.index') }}" class="btn-primary">
                    <x-icon name="layout-grid" class="h-4 w-4" />
                    Buka Grid Unit
                </a>
            </header>

            @if ($activeSessions->isEmpty())
                <div class="px-6 py-16 text-center">
                    <x-icon name="layout-grid" class="mx-auto h-10 w-10 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada unit yang disewa.</p>
                    <p class="mt-1 text-xs text-slate-600">Mulai sewa dari grid unit monitoring.</p>
                    <a href="{{ route('units.index') }}" class="btn-subtle mt-4 text-xs">
                        <x-icon name="play" class="h-3.5 w-3.5" />
                        Mulai Sewa
                    </a>
                </div>
            @else
                <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($activeSessions as $session)
                        <div class="rounded-xl border border-rose-500/25 bg-rose-500/[0.05] p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-white">{{ $session->unit->name }}</p>
                                    <p class="tabular text-xs text-slate-500">{{ $session->unit->code }}</p>
                                </div>

                                <x-badge variant="rose" dot>{{ $session->planned_minutes }} mnt</x-badge>
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs">
                                <span class="text-slate-500">Mulai</span>
                                <span class="tabular text-slate-300">{{ $session->start_time->format('H:i') }}</span>
                            </div>

                            <div class="mt-1 flex items-center justify-between text-xs">
                                <span class="text-slate-500">Tagihan berjalan</span>
                                <span class="tabular font-bold text-white">{{ \App\Support\Money::format($session->grandTotal()) }}</span>
                            </div>

                            <a
                                href="{{ route('pos.receipt', $session) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn-subtle mt-3 w-full text-[11px]"
                            >
                                <x-icon name="receipt" class="h-3.5 w-3.5" />
                                Lihat Tagihan
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ================= RIWAYAT SESI HARI INI ================= --}}
        <div class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-white/5 px-5 py-4">
                <h2 class="text-sm font-bold text-white">Sesi Selesai Shift Ini</h2>
                <span class="badge bg-white/5 text-slate-400">{{ $recentSessions->count() }}</span>
            </header>

            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Paket</th>
                            <th>Waktu</th>
                            <th class="text-right">Sewa</th>
                            <th class="text-right">F&B</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Bayar</th>
                            <th class="text-right">Nota</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($recentSessions as $session)
                            <tr>
                                <td>
                                    <p class="text-xs font-semibold text-slate-200">{{ $session->unit->name }}</p>
                                    <p class="tabular text-[10px] text-slate-500">{{ $session->unit->code }}</p>
                                </td>

                                <td class="text-xs text-slate-400">
                                    {{ $session->is_free_play ? 'Open Play' : $session->package_name }}
                                </td>

                                <td class="tabular text-xs text-slate-500">
                                    {{ $session->start_time->format('H:i') }} → {{ $session->end_time?->format('H:i') }}
                                    <br>
                                    <span class="text-slate-600">{{ $session->duration_minutes }} mnt</span>
                                </td>

                                <td class="tabular text-right text-xs text-slate-300">
                                    {{ \App\Support\Money::format($session->rental_fee, false) }}
                                </td>

                                <td class="tabular text-right text-xs text-slate-300">
                                    {{ \App\Support\Money::format($session->itemsTotal(), false) }}
                                </td>

                                <td class="tabular text-right text-xs font-bold text-white">
                                    {{ \App\Support\Money::format($session->grandTotal(), false) }}
                                </td>

                                <td class="text-center">
                                    <x-badge :variant="$session->payment_method === PaymentMethod::QRIS ? 'sky' : 'emerald'">
                                        {{ $session->payment_method?->label() }}
                                    </x-badge>
                                </td>

                                <td class="text-right">
                                    <a href="{{ route('pos.receipt', $session) }}" target="_blank" rel="noopener"
                                       class="btn-subtle text-[11px]">
                                        <x-icon name="printer" class="h-3.5 w-3.5" />
                                        Cetak
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-xs text-slate-600">
                                    Belum ada sesi yang diselesaikan pada shift ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
