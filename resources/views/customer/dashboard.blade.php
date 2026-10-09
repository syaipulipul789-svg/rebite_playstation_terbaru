@use('App\Enums\UnitStatus', 'UnitStatus')

<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Booking Konsol — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    <header class="border-b border-white/5 bg-ink-900/60 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                    <x-icon name="gamepad-2" class="h-4.5 w-4.5" />
                </span>
                <div>
                    <p class="text-sm font-extrabold uppercase tracking-widest text-white">{{ config('app.name') }}</p>
                    <p class="text-[11px] text-slate-500">Booking Konsol</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden text-xs text-slate-500 sm:block">
                    Halo, <strong class="font-semibold text-white">{{ auth()->user()->name }}</strong>
                    <span class="tabular text-slate-600">· {{ auth()->user()->phone }}</span>
                </span>

                <a href="#rentals" class="btn-subtle">
                    <x-icon name="clipboard-list" class="h-3.5 w-3.5" />
                    Permintaan Sewa
                </a>

                <a href="{{ route('account.settings') }}" class="btn-subtle">
                    <x-icon name="settings-2" class="h-3.5 w-3.5" />
                    Pengaturan Akun
                </a>

                <a href="{{ route('customer.home') }}" class="btn-subtle">
                    <x-icon name="home" class="h-3.5 w-3.5" />
                    Beranda
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-subtle">
                        <x-icon name="log-out" class="h-3.5 w-3.5" />
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main
        class="mx-auto max-w-6xl space-y-12 px-4 py-10 sm:px-6"
        x-data="bookingPanel({
            units: {{ Js::from($bookingUnits) }},
            url: @js(route('customer.bookings.store')),
            rentalUrl: @js(route('customer.rentals.store')),
            ratePackages: {{ Js::from($ratePackages) }},
            rentals: {{ Js::from($rentals) }},
        })"
        x-on:customer-units-updated.window="updateUnits($event.detail)"
    >

        @include('layouts.partials.flash')

        {{-- ================= BOOKING SAYA ================= --}}
        <section aria-label="Status booking saya">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold text-white sm:text-3xl">Booking Saya</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Slot yang sudah Anda pesan langsung terkunci — pelanggan lain tidak bisa memakai jam yang sama.
                        Setelah kasir menyetujui, jadwal unit ditandai <strong class="font-semibold text-emerald-300">Terisi</strong>
                        agar pelanggan lain bisa melihatnya. Sesi berjalan saat jam main tiba.
                    </p>
                </div>
            </div>

            <div x-data="myBookings({ initial: {{ Js::from($myBookings) }}, url: @js(route('customer.bookings.status')) })">
                <p class="mb-3 text-right text-xs text-slate-500" x-show="lastSync" x-text="lastSync"></p>
                <template x-if="bookings.length === 0">
                    <div class="card px-6 py-14 text-center">
                        <x-icon name="calendar-days" class="mx-auto h-9 w-9 text-slate-700" />
                        <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada booking.</p>
                        <p class="mt-1 text-sm text-slate-500">Pilih unit kosong di bawah untuk memesan jam main.</p>
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="booking in bookings" :key="booking.code">
                        <article class="card overflow-hidden p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Kode Booking</p>
                                    <p class="text-2xl font-extrabold tracking-wider text-brand-300" x-text="booking.code"></p>
                                </div>
                                <span class="badge" :class="booking.status.badge_class" x-text="booking.status.label"></span>
                            </div>

                            <p class="mt-3 text-sm text-slate-300" x-text="booking.status.hint"></p>

                            <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-slate-500">Konsol</dt>
                                    <dd class="font-semibold text-white">
                                        <span x-text="booking.console_name"></span>
                                        <span class="tabular text-slate-500" x-text="booking.console_code"></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Jadwal</dt>
                                    <dd class="tabular text-slate-300" x-text="booking.start_time_label + ' → ' + booking.end_time_label"></dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Perkiraan Biaya</dt>
                                    <dd class="font-bold text-white" x-text="booking.total_price_label"></dd>
                                </div>
                                <div x-show="booking.remaining_seconds !== null">
                                    <dt class="text-slate-500">Sisa Waktu</dt>
                                    <dd class="tabular font-bold text-emerald-300" x-text="remainingLabel(booking)"></dd>
                                </div>
                            </dl>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        {{-- ================= RINGKASAN STATUS ================= --}}
        <section aria-label="Ringkasan ketersediaan unit">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="card p-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total Unit</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-white">{{ $stats['total'] }}</p>
                </div>
                <a href="#units" class="card p-5 ring-1 ring-inset ring-emerald-500/30 transition hover:bg-white/5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-400/70">Siap Main</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-emerald-300"><span x-text="availableCount">{{ $stats['ready'] }}</span></p>
                    <p class="mt-1 text-[11px] text-slate-500">Bisa langsung dipesan</p>
                </a>
                <div class="card p-5 ring-1 ring-inset ring-rose-500/30">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-400/70">Terisi / Dipesan</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-rose-300"><span x-text="occupiedCount">{{ $stats['busy'] }}</span></p>
                </div>
                <div class="card p-5 ring-1 ring-inset ring-amber-500/30">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-400/70">Dalam Servis</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-amber-300">{{ $stats['maintenance'] }}</p>
                </div>
            </div>
        </section>

        {{-- ================= STATUS UNIT ================= --}}
        <section id="units" class="scroll-mt-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Pilih Unit &amp; Jadwal</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Hijau = belum terisi. Merah = sedang dipakai atau sudah dipesan untuk jadwal yang tertera. Jam lain tetap bisa dipesan.
                    </p>
                </div>
                <a href="{{ route('customer.display') }}" class="btn-subtle">
                    <x-icon name="monitor" class="h-3.5 w-3.5" />
                    Live Monitor
                </a>
            </div>

            @if ($units->isEmpty())
                <div class="card px-6 py-16 text-center">
                    <x-icon name="gamepad-2" class="mx-auto h-10 w-10 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada unit yang terdaftar.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($units as $unit)
                        @php($indicator = $unit->status->indicator())

                        <div class="card relative overflow-hidden p-5 transition duration-200 hover:border-white/15" x-bind:class="unitCard({{ $unit->id }})?.status === 'BUSY' || unitCard({{ $unit->id }})?.is_reserved ? 'ring-rose-500/40' : '{{ $indicator['ring'] }}'">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ $unit->type }}</p>
                                    <h3 class="mt-1 truncate text-base font-extrabold text-white">{{ $unit->name }}</h3>
                                    <p class="tabular mt-0.5 text-xs text-slate-500">{{ $unit->code }}</p>
                                </div>
                                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" x-bind:class="unitCard({{ $unit->id }})?.status === 'BUSY' || unitCard({{ $unit->id }})?.is_reserved ? 'bg-rose-400' : '{{ $indicator['dot'] }}'"></span>
                            </div>

                            <div class="mt-5 border-t border-white/5 pt-4">
                                @if ($unit->status === UnitStatus::READY || $unit->status === UnitStatus::BUSY)
                                    <p class="text-lg font-extrabold tracking-wide" x-bind:class="unitCard({{ $unit->id }})?.status === 'BUSY' || unitCard({{ $unit->id }})?.is_reserved ? 'text-rose-300' : 'text-emerald-300'" x-text="unitCard({{ $unit->id }})?.status === 'BUSY' || unitCard({{ $unit->id }})?.is_reserved ? 'TERISI' : 'KOSONG'"></p>
                                    <p class="mt-1 text-sm text-slate-400" x-text="unitDescription({{ $unit->id }})"></p>
                                    <p x-cloak x-show="unitCard({{ $unit->id }})?.status === 'BUSY' && unitCard({{ $unit->id }})?.is_reserved" class="mt-1 text-xs text-rose-300" x-text="'Dipesan: ' + unitCard({{ $unit->id }})?.reservation_label"></p>
                                @else
                                    <p class="text-lg font-extrabold tracking-wide text-amber-300">SERVIS</p>
                                    <p class="mt-1 text-sm text-amber-200/60">Sedang dalam perbaikan</p>
                                @endif
                            </div>

                            @if ($unit->status === UnitStatus::READY || $unit->status === UnitStatus::BUSY)
                                <button
                                    type="button"
                                    @click="openBooking({{ $unit->id }})"
                                    class="btn-primary mt-4 w-full text-xs"
                                >
                                    <x-icon name="calendar-days" class="h-3.5 w-3.5" />
                                    <span x-text="unitCard({{ $unit->id }})?.status === 'BUSY' || unitCard({{ $unit->id }})?.is_reserved ? 'Booking Jam Lain' : 'Booking Konsol Ini'"></span>
                                </button>

                                <button
                                    type="button"
                                    @click="openRental({{ $unit->id }})"
                                    class="btn-subtle mt-2 w-full justify-center text-xs"
                                >
                                    <x-icon name="clipboard-list" class="h-3.5 w-3.5" />
                                    Ajukan Sewa
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ================= MODAL BOOKING ================= --}}
            <div
                x-cloak
                x-show="open"
                class="fixed inset-0 z-50"
                x-transition.opacity.duration.200ms
                x-on:keydown.escape.window="close()"
            >
                <div
                    class="absolute inset-0 bg-black/70 backdrop-blur-sm"
                    x-show="open"
                    x-transition.opacity.duration.200ms
                    x-on:click="close()"
                ></div>

                <div
                    class="absolute inset-x-0 bottom-0 max-h-[90vh] overflow-y-auto sm:inset-x-auto sm:bottom-auto sm:top-1/2 sm:left-1/2 sm:max-h-[85vh] sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-4 opacity-0 sm:scale-95 sm:translate-y-0"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                    x-transition:leave-end="translate-y-4 opacity-0 sm:scale-95 sm:translate-y-0"
                >
                    <div class="card border-white/10 bg-ink-900 p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500" x-text="unit?.type"></p>
                                <h3 class="truncate text-base font-extrabold text-white" x-text="unit?.name"></h3>
                                <p class="tabular text-xs text-slate-500" x-text="unit?.code"></p>
                            </div>

                            <button type="button" x-on:click="close()" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-white">
                                <x-icon name="x" class="h-4 w-4" />
                            </button>
                        </div>

                        <template x-if="unit?.status === 'BUSY' || unit?.is_reserved">
                            <p class="mt-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-xs text-rose-200">
                                <x-icon name="clock" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span x-text="unit.status === 'BUSY' ? 'Unit sedang dipakai. Pilih jam setelah sesi selesai.' : 'Sudah dipesan: ' + unit.reservation_label + '. Pilih jam di luar jadwal tersebut.'"></span>
                            </p>
                        </template>

                        {{-- RINGKASAN SUKSES BOOKING --}}
                        <template x-if="mode === 'booking' && result">
                            <div class="mt-4">
                                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                                    <p class="flex items-center gap-2 text-sm font-bold text-emerald-300">
                                        <x-icon name="check-circle-2" class="h-4 w-4" />
                                        Booking berhasil!
                                    </p>
                                    <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Kode Booking</p>
                                    <p class="text-2xl font-extrabold tracking-wider text-brand-300" x-text="result.booking_code"></p>

                                    <div class="mt-3">
                                        <span class="badge" :class="result.status.badge_class" x-text="result.status.label"></span>
                                        <p class="mt-2 text-xs text-slate-400" x-text="result.status.hint"></p>
                                    </div>

                                    <dl class="mt-4 space-y-2 text-sm">
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Konsol</dt>
                                            <dd class="font-semibold text-slate-100" x-text="result.console_name"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Jadwal</dt>
                                            <dd class="tabular text-right text-slate-100" x-text="result.start_time_label + ' → ' + result.end_time_label"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Estimasi Biaya</dt>
                                            <dd class="font-bold text-white" x-text="result.total_price_label"></dd>
                                        </div>
                                    </dl>
                                </div>

                                <p class="mt-3 text-xs text-slate-500">
                                    Slot jam ini sudah terkunci untuk Anda. Setelah disetujui kasir, jadwal unit terlihat
                                    <strong class="font-semibold text-emerald-300">Terisi</strong> bagi pelanggan lain.
                                    Tunjukkan kode booking ini saat datang.
                                </p>

                                <button type="button" x-on:click="close()" class="btn-primary mt-4 w-full">
                                    Selesai
                                </button>
                            </div>
                        </template>

                        {{-- RINGKASAN SUKSES PERMINTAAN SEWA --}}
                        <template x-if="mode === 'rental' && rentalResult">
                            <div class="mt-4">
                                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                                    <p class="flex items-center gap-2 text-sm font-bold text-emerald-300">
                                        <x-icon name="check-circle-2" class="h-4 w-4" />
                                        Permintaan sewa terkirim!
                                    </p>
                                    <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Kode Permintaan</p>
                                    <p class="text-2xl font-extrabold tracking-wider text-brand-300" x-text="rentalResult.rental_code"></p>

                                    <div class="mt-3">
                                        <span class="badge" :class="rentalResult.status.badge_class" x-text="rentalResult.status.label"></span>
                                    </div>

                                    <dl class="mt-4 space-y-2 text-sm">
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Unit</dt>
                                            <dd class="font-semibold text-slate-100" x-text="rentalResult.unit_name"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Jadwal Sewa</dt>
                                            <dd class="tabular text-right text-slate-100" x-text="rentalResult.start_time_label + ' → ' + rentalResult.end_time_label"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Paket</dt>
                                            <dd class="text-slate-100" x-text="rentalResult.package_name ?? 'Tanpa Paket'"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <dt class="text-slate-500">Estimasi Biaya</dt>
                                            <dd class="font-bold text-white" x-text="rentalResult.total_price_label"></dd>
                                        </div>
                                    </dl>
                                </div>

                                <p class="mt-3 text-xs text-slate-500">
                                    Kasir akan memeriksa ketersediaan unit terlebih dahulu. Pantau statusnya di bagian
                                    <strong class="font-semibold text-white">Permintaan Sewa Saya</strong> di bawah, lalu
                                    tunjukkan kode ini saat datang.
                                </p>

                                <button type="button" x-on:click="close()" class="btn-primary mt-4 w-full">
                                    Selesai
                                </button>
                            </div>
                        </template>

                        {{-- FORM BOOKING --}}
                        <template x-if="mode === 'booking' && !result">
                            <form class="mt-4 space-y-4" x-on:submit.prevent="submit()">
                                <div class="rounded-xl border border-white/5 bg-ink-850 px-4 py-3 text-xs">
                                    <p class="text-slate-500">Nama pemesan</p>
                                    <p class="mt-0.5 font-semibold text-white">{{ auth()->user()->name }}</p>
                                    <p class="tabular mt-1 text-slate-600">{{ auth()->user()->phone }}</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="bk-start" class="label">Jam Mulai</label>
                                        <input id="bk-start" type="datetime-local" class="input" x-model="form.start_time">
                                        <p class="mt-1 text-xs text-rose-400" x-text="fieldError('start_time')" x-show="fieldError('start_time')"></p>
                                    </div>

                                    <div>
                                        <label for="bk-duration" class="label">Durasi (Jam)</label>
                                        <input id="bk-duration" type="number" min="1" max="12" class="input" x-model.number="form.duration_hours">
                                        <p class="mt-1 text-xs text-rose-400" x-text="fieldError('duration_hours')" x-show="fieldError('duration_hours')"></p>
                                    </div>
                                </div>

                                <div>
                                    <label for="bk-notes" class="label">Catatan <span class="normal-case text-slate-600">(opsional)</span></label>
                                    <textarea id="bk-notes" rows="2" class="input resize-none" placeholder="Pesan tambahan, mis. minta remote PS5" x-model="form.notes"></textarea>
                                    <p class="mt-1 text-xs text-rose-400" x-text="fieldError('notes')" x-show="fieldError('notes')"></p>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-white/5 bg-ink-850 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Estimasi Biaya</p>
                                        <p class="text-[10px] text-slate-600" x-text="unit?.is_free ? 'Open Play · gratis' : window.Rebite.rupiah(unit?.hourly_rate ?? 0) + ' / jam'"></p>
                                    </div>
                                    <p class="text-lg font-extrabold text-brand-300" x-text="pricePreview"></p>
                                </div>

                                <p class="rounded-xl bg-rose-500/10 px-3 py-2 text-xs text-rose-200" x-text="error" x-show="error"></p>

                                <button type="submit" class="btn-primary w-full" x-bind:disabled="submitting">
                                    <span x-text="submitting ? 'Mengirim...' : 'Kunci Slot & Booking'"></span>
                                </button>

                                <p class="text-center text-[11px] text-slate-600">
                                    Slot langsung terkunci begitu dikirim. Pembayaran di kasir saat datang.
                                </p>
                            </form>
                        </template>

                        {{-- FORM PERMINTAAN SEWA --}}
                        <template x-if="mode === 'rental' && !rentalResult">
                            <form class="mt-4 space-y-4" x-on:submit.prevent="submitRental()">
                                <div class="rounded-xl border border-white/5 bg-ink-850 px-4 py-3 text-xs">
                                    <p class="text-slate-500">Nama pemesan</p>
                                    <p class="mt-0.5 font-semibold text-white">{{ auth()->user()->name }}</p>
                                    <p class="tabular mt-1 text-slate-600">{{ auth()->user()->phone }}</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="rr-start" class="label">Waktu Mulai</label>
                                        <input id="rr-start" type="datetime-local" class="input" x-model="rentalForm.start_time">
                                        <p class="mt-1 text-xs text-rose-400" x-text="fieldError('start_time')" x-show="fieldError('start_time')"></p>
                                    </div>

                                    <div>
                                        <label for="rr-end" class="label">Waktu Selesai</label>
                                        <input id="rr-end" type="datetime-local" class="input" x-model="rentalForm.end_time">
                                        <p class="mt-1 text-xs text-rose-400" x-text="fieldError('end_time')" x-show="fieldError('end_time')"></p>
                                    </div>
                                </div>

                                <div>
                                    <label for="rr-package" class="label">Paket <span class="normal-case text-slate-600">(opsional)</span></label>
                                    <select id="rr-package" class="input" x-model="rentalForm.package_id">
                                        <option value="">Tanpa Paket</option>
                                        <template x-for="pkg in ratePackages" :key="pkg.id">
                                            <option :value="pkg.id" x-text="pkg.name + ' · ' + pkg.duration_label + ' · ' + window.Rebite.rupiah(pkg.price)"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-rose-400" x-text="fieldError('package_id')" x-show="fieldError('package_id')"></p>
                                </div>

                                <div>
                                    <label for="rr-notes" class="label">Catatan <span class="normal-case text-slate-600">(opsional)</span></label>
                                    <textarea id="rr-notes" rows="2" class="input resize-none" placeholder="Keterangan tambahan untuk kasir" x-model="rentalForm.notes"></textarea>
                                    <p class="mt-1 text-xs text-rose-400" x-text="fieldError('notes')" x-show="fieldError('notes')"></p>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-white/5 bg-ink-850 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Estimasi Biaya</p>
                                        <p class="text-[10px] text-slate-600" x-text="(selectedPackage ? selectedPackage.name + ' · ' + selectedPackage.duration_label : 'Tanpa paket') + ' · ' + rentalDurationMinutes + ' menit'"></p>
                                    </div>
                                    <p class="text-lg font-extrabold text-brand-300" x-text="rentalPricePreview || '—'"></p>
                                </div>

                                <p class="rounded-xl bg-rose-500/10 px-3 py-2 text-xs text-rose-200" x-text="error" x-show="error"></p>

                                <button type="submit" class="btn-primary w-full" x-bind:disabled="submitting || rentalDurationMinutes < 60">
                                    <span x-text="submitting ? 'Mengirim...' : 'Kirim Permintaan Sewa'"></span>
                                </button>

                                <p class="text-center text-[11px] text-slate-600">
                                    Durasi minimal 1 jam. Permintaan dikirim ke kasir untuk konfirmasi ketersediaan unit.
                                </p>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================= PERMINTAAN SEWA ================= --}}
        <section id="rentals" class="scroll-mt-8" aria-label="Permintaan sewa saya">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Permintaan Sewa Saya</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Diajukan lewat tombol <strong class="font-semibold text-white">Ajukan Sewa</strong> pada kartu unit di atas.
                    Kasir perlu mengonfirmasi sebelum sewa disetujui.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr,320px]">
                <div class="card overflow-hidden">
                    <template x-if="rentals.length === 0">
                        <div class="px-6 py-12 text-center">
                            <x-icon name="clipboard-list" class="mx-auto h-9 w-9 text-slate-700" />
                            <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada permintaan sewa.</p>
                            <p class="mt-1 text-sm text-slate-500">Pilih unit di bagian "Pilih Unit &amp; Jadwal" lalu tekan "Ajukan Sewa".</p>
                        </div>
                    </template>

                    <div class="divide-y divide-white/5" x-show="rentals.length > 0">
                        <template x-for="rental in rentals" :key="rental.rental_code">
                            <article class="px-6 py-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-extrabold text-white" x-text="rental.rental_code"></h3>
                                    <span class="badge" :class="rental.status.badge_class" x-text="rental.status.label"></span>
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-slate-400">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="gamepad-2" class="h-4 w-4" />
                                        <span x-text="rental.unit_name + ' (' + rental.unit_code + ')'"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 tabular">
                                        <x-icon name="clock" class="h-4 w-4" />
                                        <span x-text="rental.start_time_label + ' → ' + rental.end_time_label"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="tags" class="h-4 w-4" />
                                        <span x-text="rental.package_name ?? 'Tanpa Paket'"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="receipt" class="h-4 w-4" />
                                        <span x-text="rental.total_price_label"></span>
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-slate-500" x-show="rental.notes">
                                    <span class="text-slate-600">Catatan:</span>
                                    <span x-text="rental.notes"></span>
                                </p>
                                <p class="mt-2 text-xs text-slate-600">
                                    <span x-text="'Diajukan ' + rental.created_at_label"></span>
                                </p>
                            </article>
                        </template>
                    </div>
                </div>

                <aside class="card h-fit p-6">
                    <h3 class="text-sm font-extrabold uppercase tracking-widest text-slate-400">Keterangan Status</h3>
                    <ul class="mt-4 space-y-2 text-sm text-slate-400">
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            Menunggu Konfirmasi — kasir perlu menyetujui permintaan Anda
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            Terkonfirmasi — permintaan disetujui
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                            Dibatalkan — permintaan tidak disetujui
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                            Selesai — permintaan telah diproses
                        </li>
                    </ul>
                </aside>
            </div>
        </section>
    </main>

    <x-toast />
</body>
</html>