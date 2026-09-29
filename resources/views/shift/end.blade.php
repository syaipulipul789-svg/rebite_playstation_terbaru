@extends('layouts.app')

@section('title', 'Rekonsiliasi Shift')
@section('page-title', 'Rekonsiliasi Akhir Shift')
@section('page-subtitle', 'Cocokkan uang fisik di laci dengan ekspektasi kas sistem')

@section('content')
    <div
        x-data="reconciliation({
            startingCash: @js((float) $preview['starting_cash']),
            cashRevenue: @js((float) $preview['cash_revenue']),
            qrisRevenue: @js((float) $preview['qris_revenue']),
        })"
        class="grid gap-5 lg:grid-cols-5"
    >
        {{-- ================= KALKULASI BACKEND ================= --}}
        <div class="space-y-5 lg:col-span-3">

            <x-card title="Rincian Rekonsiliasi Shift" icon="wallet" description="Dihitung otomatis oleh sistem dari sesi sewa yang sudah selesai">
                <dl class="divide-y divide-white/5">
                    <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <dt class="text-sm text-slate-400">Modal Awal Kas</dt>
                        <dd class="tabular text-sm font-semibold text-slate-100">
                            {{ \App\Support\Money::format($preview['starting_cash']) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <dt class="flex items-center gap-2 text-sm text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Total Pendapatan Tunai Shift
                        </dt>
                        <dd class="tabular text-sm font-semibold text-emerald-300">
                            {{ \App\Support\Money::format($preview['cash_revenue']) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <dt class="flex items-center gap-2 text-sm text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                            Total Pendapatan QRIS
                            <span class="text-[10px] uppercase tracking-wider text-slate-600">(tidak masuk laci)</span>
                        </dt>
                        <dd class="tabular text-sm font-semibold text-sky-300">
                            {{ \App\Support\Money::format($preview['qris_revenue']) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <dt class="text-sm text-slate-400">Jumlah Transaksi Selesai</dt>
                        <dd class="tabular text-sm font-semibold text-slate-100">
                            {{ $preview['session_count'] }} sesi
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4 bg-brand-500/[0.07] px-5 py-4">
                        <dt class="text-sm font-bold text-white">Total Ekspektasi Kas</dt>
                        <dd class="tabular text-lg font-extrabold text-white">
                            {{ \App\Support\Money::format($preview['expected_cash']) }}
                        </dd>
                    </div>
                </dl>

                <p class="border-t border-white/5 px-5 py-3 text-xs text-slate-500">
                    <strong class="font-semibold text-slate-400">Formula:</strong>
                    Modal Awal + Total Pendapatan Tunai = {{ \App\Support\Money::format($preview['starting_cash']) }}
                    + {{ \App\Support\Money::format($preview['cash_revenue']) }}
                    = <strong class="text-slate-300">{{ \App\Support\Money::format($preview['expected_cash']) }}</strong>
                </p>
            </x-card>

            {{-- ================= FORM INPUT KASIR ================= --}}
            <form
                method="POST"
                action="{{ route('shift.end.store') }}"
                class="card p-6"
                x-on:submit="if (! submit()) $event.preventDefault()"
            >
                @csrf

                <input type="hidden" name="expected_cash" x-bind:value="expected">

                <h2 class="text-sm font-bold text-white">Input Uang Fisik di Laci Kasir</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Hitung seluruh uang tunai di laci, lalu masukkan nominalnya.
                </p>

                <div class="mt-5">
                    <label for="actual_physical_cash" class="label">
                        Jumlah Uang Fisik Riil di Laci Kasir
                        <span class="text-rose-400">*</span>
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-base font-bold text-slate-400">
                            Rp
                        </span>

                        <input
                            id="actual_physical_cash"
                            name="actual_physical_cash_display"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-model="actualInput"
                            x-on:input="formatInput($event)"
                            x-bind:class="{
                                'border-rose-500/60 focus:ring-rose-500/25': noteError,
                                'border-emerald-500/60 focus:ring-emerald-500/25': isBalanced,
                                'border-amber-500/60 focus:ring-amber-500/25': hasDiscrepancy,
                            }"
                            class="input tabular pl-12 text-xl font-bold"
                            placeholder="0"
                            autofocus
                        >
                    </div>

                    @error('actual_physical_cash')
                        <p class="mt-1.5 text-xs font-medium text-rose-400">{{ $message }}</p>
                    @enderror

                    {{-- Nilai numerik murni untuk submit; input display sengaja
                         diberi name berbeda agar tidak terkirim dua kali. --}}
                    <input type="hidden" name="actual_physical_cash" x-bind:value="actualValue">
                </div>

                {{-- ================= HASIL SELISIH LIVE ================= --}}
                <div class="mt-4 rounded-xl border p-4 transition" :class="{
                    'border-white/5 bg-ink-900': actualInput === '',
                    'border-emerald-500/30 bg-emerald-500/[0.07]': isBalanced,
                    'border-amber-500/30 bg-amber-500/[0.07]': hasDiscrepancy,
                }">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Selisih (Discrepancy)
                        </span>

                        <span x-show="actualInput !== ''" class="text-[10px] font-bold uppercase tracking-widest"
                              :class="hasDiscrepancy ? 'text-amber-300' : 'text-emerald-300'"
                              x-text="discrepancyDirection"></span>
                    </div>

                    <p class="tabular mt-1.5 text-2xl font-extrabold" :class="{
                        'text-slate-600': actualInput === '',
                        'text-emerald-300': isBalanced,
                        'text-amber-300': hasDiscrepancy,
                    }" x-text="actualInput === '' ? '—' : discrepancyLabel"></p>

                    <p x-show="isBalanced" class="mt-1.5 flex items-center gap-1.5 text-xs text-emerald-300/80">
                        <x-icon name="check-circle-2" class="h-3.5 w-3.5" />
                        Kas sesuai dengan ekspektasi sistem.
                    </p>

                    <p x-show="hasDiscrepancy" class="mt-1.5 text-xs text-amber-200/70">
                        Uang fisik <strong x-text="discrepancyDirection"></strong> dari ekspektasi kas.
                        Kolom catatan keterangan selisih menjadi <strong>wajib</strong> diisi.
                    </p>
                </div>

                {{-- ================= CATATAN WAJIB KETIKA SELISIH != 0 ================= --}}
                <div x-cloak x-show="showNote" x-transition class="mt-5">
                    <label for="note" class="label">
                        Catatan Keterangan Selisih
                        <span class="text-rose-400">*</span>
                    </label>

                    <textarea
                        id="note"
                        name="note"
                        rows="3"
                        x-model="note"
                        x-bind:class="noteError ? 'border-rose-500/60 focus:ring-rose-500/25' : ''"
                        class="input resize-y"
                        placeholder="Contoh: Selisih kurang karena salah hitung kembalian pelanggan, atau selisih lebih karena ada uang tertinggal di laci."
                    ></textarea>

                    <p x-show="noteError" class="mt-1.5 text-xs font-medium text-rose-400">
                        Selisih kas tidak nol. Wajib menyertakan catatan keterangan selisih.
                    </p>

                    <p class="mt-1.5 text-xs text-slate-500">
                        Catatan ini dikirim ke audit log Owner dan tidak dapat diedit setelah shift ditutup.
                    </p>

                    @error('note')
                        <p class="mt-1.5 text-xs font-medium text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex flex-wrap gap-3 border-t border-white/5 pt-5">
                    <a href="{{ route('units.index') }}" class="btn-ghost">
                        <x-icon name="chevron-left" class="h-4 w-4" />
                        Kembali ke Grid Unit
                    </a>

                    <button type="submit" class="btn-danger ml-auto">
                        <x-icon name="lock" class="h-4 w-4" />
                        Tutup Shift &amp; Kunci Rekap
                    </button>
                </div>
            </form>
        </div>

        {{-- ================= SIDEBAR KANAN ================= --}}
        <div class="space-y-5 lg:col-span-2">
            <x-card title="Info Shift Berjalan" icon="activity">
                <dl class="space-y-3 p-5 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Kasir</dt>
                        <dd class="font-semibold text-slate-200">{{ auth()->user()->name }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Mulai Shift</dt>
                        <dd class="tabular font-semibold text-slate-200">
                            {{ $shift->start_time->format('d M Y H:i') }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Durasi</dt>
                        <dd class="tabular font-semibold text-slate-200">
                            {{ \App\Support\Duration::humanize($shift->durationInMinutes()) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-badge variant="sky" dot>{{ $shift->status->label() }}</x-badge></dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Sewa Masih Berjalan" icon="alert-triangle">
                @php $runningCount = $shift->rentalSessions()->where('status', \App\Enums\RentalSessionStatus::RUNNING)->count(); @endphp

                @if ($runningCount > 0)
                    <div class="p-5">
                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/[0.07] p-4">
                            <p class="text-sm font-semibold text-amber-100">
                                {{ $runningCount }} unit masih berjalan
                            </p>
                            <p class="mt-1 text-xs text-amber-200/70">
                                Selesaikan semua sewa (pilih metode pembayaran) sebelum menutup shift.
                                Shift tidak bisa dikunci selama masih ada unit aktif.
                            </p>
                        </div>

                        <a href="{{ route('pos.index') }}" class="btn-warning mt-4 w-full">
                            <x-icon name="receipt" class="h-4 w-4" />
                            Buka Kasir / POS
                        </a>
                    </div>
                @else
                    <div class="px-5 py-10 text-center">
                        <x-icon name="check-circle-2" class="mx-auto h-8 w-8 text-emerald-500/50" />
                        <p class="mt-3 text-sm text-emerald-300/80">Semua unit sudah selesai.</p>
                        <p class="mt-1 text-xs text-slate-500">Shift siap untuk direkonsiliasi.</p>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
