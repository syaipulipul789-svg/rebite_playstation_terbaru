@extends('layouts.app')

@section('title', 'Mulai Shift')
@section('page-title', 'Mulai Shift')
@section('page-subtitle', 'Gatekeeper — input modal awal kas sebelum membuka halaman operasional')

@section('content')
    <div class="mx-auto max-w-3xl">

        <div class="mb-6 flex items-start gap-4 rounded-2xl border border-amber-500/25 bg-amber-500/[0.07] p-5">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-500/15 text-amber-300">
                <x-icon name="key-round" class="h-5 w-5" />
            </span>

            <div>
                <h2 class="text-sm font-bold text-amber-100">Shift belum dimulai</h2>
                <p class="mt-1 text-sm text-amber-200/70">
                    Halaman <strong class="font-semibold text-amber-100">Grid Unit</strong>,
                    <strong class="font-semibold text-amber-100">Kasir/POS</strong>, dan
                    <strong class="font-semibold text-amber-100">Rekonsiliasi</strong> terkunci sampai Anda
                    menginput nominal uang yang tersedia di laci kasir saat ini.
                </p>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-5">
            {{-- ================= FORM ================= --}}
            <div class="lg:col-span-3">
                <div class="card p-6">
                    <form method="POST" action="{{ route('shift.start.store') }}" class="space-y-5">
                        @csrf

                        <x-input
                            name="starting_cash"
                            label="Modal Awal Kas (Starting Cash)"
                            prefix="Rp"
                            inputmode="numeric"
                            :value="old('starting_cash', '0')"
                            hint="Hitung uang tunai yang ada di laci kasir sebelum shift berjalan."
                            required
                            autofocus
                        />

                        <div class="flex flex-wrap gap-2">
                            @foreach ([100000, 150000, 200000, 250000, 300000, 500000] as $preset)
                                <button
                                    type="button"
                                    x-on:click="document.getElementById('starting_cash').value = '{{ number_format($preset, 0, ',', '.') }}'"
                                    class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:border-brand-500/40 hover:bg-brand-500/10 hover:text-white"
                                >
                                    {{ \App\Support\Money::format($preset) }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-3 border-t border-white/5 pt-5">
                            <button type="submit" class="btn-primary flex-1">
                                <x-icon name="play" class="h-4 w-4" />
                                Mulai Shift Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ================= INFO SHIFT TERDAHULU ================= --}}
            <div class="lg:col-span-2">
                <x-card title="Shift Terakhir Anda" icon="history">
                    @if ($lastShift)
                        <dl class="space-y-3 p-5 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Waktu</dt>
                                <dd class="tabular font-semibold text-slate-200">
                                    {{ $lastShift->start_time->format('d M Y H:i') }} –
                                    {{ $lastShift->end_time?->format('H:i') }}
                                </dd>
                            </div>

                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Modal Awal</dt>
                                <dd class="tabular font-semibold text-slate-200">
                                    {{ \App\Support\Money::format($lastShift->starting_cash) }}
                                </dd>
                            </div>

                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Pendapatan</dt>
                                <dd class="tabular font-semibold text-emerald-300">
                                    {{ \App\Support\Money::format($lastShift->totalSystemRevenue()) }}
                                </dd>
                            </div>

                            <div class="flex items-center justify-between border-t border-white/5 pt-3">
                                <dt class="text-slate-500">Selisih Kas</dt>
                                <dd>
                                    @if ($lastShift->hasDiscrepancy())
                                        <x-badge variant="{{ $lastShift->discrepancy > 0 ? 'sky' : 'rose' }}">
                                            {{ \App\Support\Money::formatSigned($lastShift->discrepancy) }}
                                        </x-badge>
                                    @else
                                        <x-badge variant="emerald" dot>Se sesuai</x-badge>
                                    @endif
                                </dd>
                            </div>

                            @if ($lastShift->note)
                                <div class="rounded-lg border border-white/5 bg-ink-900 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">
                                        Catatan Selisih
                                    </p>
                                    <p class="mt-1.5 text-xs leading-relaxed text-slate-400">{{ $lastShift->note }}</p>
                                </div>
                            @endif
                        </dl>
                    @else
                        <div class="px-5 py-10 text-center">
                            <x-icon name="history" class="mx-auto h-8 w-8 text-slate-700" />
                            <p class="mt-3 text-sm text-slate-500">Belum ada riwayat shift.</p>
                        </div>
                    @endif
                </x-card>
            </div>
        </div>
    </div>
@endsection
