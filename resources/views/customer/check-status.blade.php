@use('App\Support\Duration', 'Duration')
@use('App\Support\Money', 'Money')

<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Cek status booking rental PS4 & PS5 di Rebite Playstation.">
    <meta name="robots" content="noindex">

    <title>Cek Status Booking — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    {{-- ================= HEADER ================= --}}
    <header class="grid-noise relative overflow-hidden border-b border-white/5">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-950/50 via-transparent to-ink-950"></div>
        <div class="pointer-events-none absolute -top-32 left-1/2 h-80 w-[36rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>

        <div class="relative mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <nav class="flex items-center justify-between">
                <a href="{{ route('customer.home') }}" class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                        <x-icon name="gamepad-2" class="h-4.5 w-4.5" />
                    </span>
                    <span class="text-sm font-extrabold uppercase tracking-widest text-white">{{ config('app.name') }}</span>
                </a>

                <a href="{{ route('login') }}" class="btn-subtle">Login Kasir</a>
            </nav>

            <div class="pt-10 text-center">
                <span class="badge bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                    <x-icon name="search" class="h-3.5 w-3.5" />
                    Status Booking
                </span>

                <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                    Cek Status Booking
                </h1>

                <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-slate-400">
                    Masukkan kode booking dan nomor WhatsApp yang kamu pakai saat
                    membuat booking. Nomor WhatsApp dipakai untuk memastikan booking
                    itu benar milikmu.
                </p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6">
        @if (! empty($error))
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-950/40 px-4 py-3">
                <x-icon name="circle-alert" class="mt-0.5 h-4 w-4 shrink-0 text-rose-300" />
                <p class="text-sm font-medium text-rose-100">{{ $error }}</p>
            </div>
        @endif

        {{-- ================= HASIL PENCARIAN ================= --}}
        @if ($booking !== null)
            @php
                $status = $booking->customerStatus();
                $remaining = $status['is_occupied'] ? $booking->rentalSession->remainingSeconds() : null;
            @endphp

            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-white/5 px-5 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Kode Booking</p>
                        <p class="text-2xl font-extrabold tracking-wider text-brand-300">{{ $booking->bookingCode() }}</p>
                    </div>

                    <span class="badge {{ $status['badge_class'] }}">{{ $status['label'] }}</span>
                </div>

                <p class="px-5 pt-4 text-sm text-slate-300">{{ $status['hint'] }}</p>

                <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Nama Pemesan</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">{{ $booking->customer_name }}</dd>
                    </div>

                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Konsol</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">
                            {{ $booking->console?->name }}
                            <span class="tabular text-slate-500">{{ $booking->console?->code }}</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Jadwal</dt>
                        <dd class="tabular mt-1 text-sm text-slate-300">
                            {{ $booking->start_time->format('d M Y H:i') }} → {{ $booking->end_time->format('d M Y H:i') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Estimasi Jam Bermain</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">
                            {{ Duration::humanize($booking->durationMinutes()) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Perkiraan Biaya</dt>
                        <dd class="mt-1 text-sm font-bold text-white">{{ Money::format($booking->total_price) }}</dd>
                    </div>

                    @if ($remaining !== null)
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Sisa Waktu</dt>
                            <dd class="tabular mt-1 text-sm font-bold text-emerald-300" data-countdown-seconds="{{ $remaining }}">
                                {{ Duration::toHms($remaining) }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($booking->notes)
                    <div class="border-t border-white/5 px-5 py-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Catatan</p>
                        <p class="mt-1 text-sm text-slate-300">{{ $booking->notes }}</p>
                    </div>
                @endif

                <footer class="border-t border-white/5 bg-white/[0.02] px-5 py-3">
                    <p class="text-xs text-slate-500">
                        Tunjukkan kode booking ini ke kasir saat datang. Pembayaran
                        dilakukan langsung di tempat.
                    </p>
                </footer>
            </section>
        @endif

        {{-- ================= FORM PENCARIAN ================= --}}
        <section class="card p-5 sm:p-6">
            <h2 class="text-base font-bold text-white">
                {{ $booking !== null ? 'Cari Booking Lain' : 'Masukkan Data Booking' }}
            </h2>
            <p class="mt-1 text-sm text-slate-400">
                Kode booking dimulai dengan <span class="tabular font-semibold text-brand-300">BK-</span> diikuti 4 angka.
            </p>

            <form method="POST" action="{{ route('booking.check.search') }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="booking_code" class="label">Kode Booking</label>
                    <input
                        id="booking_code"
                        name="booking_code"
                        type="text"
                        inputmode="numeric"
                        class="input tabular uppercase {{ $errors->has('booking_code') ? 'border-rose-500/60' : '' }}"
                        placeholder="BK-0004"
                        autocomplete="off"
                        spellcheck="false"
                        maxlength="20"
                        value="{{ old('booking_code') }}"
                        x-data="bookingCodeInput(@js(old('booking_code')))"
                        x-model="value"
                        x-on:input="format()"
                        required
                    >
                    @error('booking_code')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="customer_phone" class="label">Nomor WhatsApp</label>
                    <input
                        id="customer_phone"
                        name="customer_phone"
                        type="tel"
                        inputmode="tel"
                        class="input tabular {{ $errors->has('customer_phone') ? 'border-rose-500/60' : '' }}"
                        placeholder="081234567890"
                        autocomplete="tel"
                        maxlength="30"
                        value="{{ old('customer_phone') }}"
                        required
                    >
                    @error('customer_phone')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primary w-full">
                    <x-icon name="search" class="h-4 w-4" />
                    Cek Status
                </button>
            </form>
        </section>

        <p class="text-center text-xs text-slate-500">
            <a href="{{ route('customer.home') }}" class="font-semibold text-brand-300 hover:text-brand-200">Kembali ke beranda</a>
        </p>
    </main>

</body>
</html>
