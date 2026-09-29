@extends('layouts.app')

@section('title', 'Ringkasan Shift')
@section('page-title', 'Shift Ditutup')
@section('page-subtitle', 'Rekap rekonsiliasi shift ' . $shift->start_time->format('d F Y'))

@section('content')
    <div class="mx-auto max-w-3xl">

        <div class="mb-6 flex flex-col items-center text-center">
            <span @class([
                'grid h-16 w-16 place-items-center rounded-2xl',
                'bg-emerald-500/15 text-emerald-300' => ! $shift->hasDiscrepancy(),
                'bg-amber-500/15 text-amber-300' => $shift->hasDiscrepancy(),
            ])>
                <x-icon :name="$shift->hasDiscrepancy() ? 'triangle-alert' : 'check-circle-2'" class="h-8 w-8" />
            </span>

            <h2 class="mt-4 text-xl font-extrabold text-white">
                {{ $shift->hasDiscrepancy() ? 'Shift ditutup dengan selisih' : 'Shift ditutup dengan kas sesuai' }}
            </h2>

            <p class="mt-1.5 text-sm text-slate-500">
                Rekap sudah terkunci dan dikirim ke audit log Owner.
            </p>
        </div>

        <x-card>
            <dl class="divide-y divide-white/5">
                <div class="flex items-center justify-between px-5 py-3.5">
                    <dt class="text-sm text-slate-400">Waktu Shift</dt>
                    <dd class="tabular text-sm font-semibold text-slate-100">
                        {{ $shift->start_time->format('d M Y H:i') }} – {{ $shift->end_time?->format('H:i') }}
                    </dd>
                </div>

                <div class="flex items-center justify-between px-5 py-3.5">
                    <dt class="text-sm text-slate-400">Modal Awal</dt>
                    <dd class="tabular text-sm font-semibold text-slate-100">
                        {{ \App\Support\Money::format($shift->starting_cash) }}
                    </dd>
                </div>

                <div class="flex items-center justify-between px-5 py-3.5">
                    <dt class="text-sm text-slate-400">Pendapatan Tunai</dt>
                    <dd class="tabular text-sm font-semibold text-emerald-300">
                        {{ \App\Support\Money::format($shift->system_cash_revenue) }}
                    </dd>
                </div>

                <div class="flex items-center justify-between px-5 py-3.5">
                    <dt class="text-sm text-slate-400">Pendapatan QRIS</dt>
                    <dd class="tabular text-sm font-semibold text-sky-300">
                        {{ \App\Support\Money::format($shift->system_qris_revenue) }}
                    </dd>
                </div>

                <div class="flex items-center justify-between bg-brand-500/[0.07] px-5 py-3.5">
                    <dt class="text-sm font-bold text-white">Total Ekspektasi Kas</dt>
                    <dd class="tabular text-base font-extrabold text-white">
                        {{ \App\Support\Money::format($shift->expectedCash()) }}
                    </dd>
                </div>

                <div class="flex items-center justify-between px-5 py-3.5">
                    <dt class="text-sm text-slate-400">Uang Fisik Riil di Laci</dt>
                    <dd class="tabular text-sm font-semibold text-slate-100">
                        {{ \App\Support\Money::format($shift->actual_physical_cash) }}
                    </dd>
                </div>

                <div @class([
                    'flex items-center justify-between px-5 py-4',
                    'bg-emerald-500/[0.07]' => ! $shift->hasDiscrepancy(),
                    'bg-amber-500/[0.07]' => $shift->hasDiscrepancy(),
                ])>
                    <dt class="text-sm font-bold text-white">Selisih Kas</dt>
                    <dd>
                        @if ($shift->hasDiscrepancy())
                            <x-badge variant="amber" dot>
                                {{ \App\Support\Money::formatSigned($shift->discrepancy) }}
                            </x-badge>
                        @else
                            <x-badge variant="emerald" dot>Rp 0 — Se sesuai</x-badge>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($shift->note)
                <div class="border-t border-white/5 p-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">
                        Catatan Keterangan Selisih
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ $shift->note }}</p>
                </div>
            @endif
        </x-card>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('units.index') }}" class="btn-primary flex-1">
                <x-icon name="layout-grid" class="h-4 w-4" />
                Mulai Shift Baru
            </a>

            <button type="button" x-data x-on:click="window.print()" class="btn-ghost">
                <x-icon name="printer" class="h-4 w-4" />
                Cetak Rekap
            </button>
        </div>
    </div>
@endsection
