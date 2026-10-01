@use('App\Enums\ProductCategory', 'ProductCategory')
@use('App\Enums\UnitStatus', 'UnitStatus')
@use('App\Support\Money', 'Money')

<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Rebite Playstation — rental PS4 & PS5, lengkap dengan paket sewa, snack, dan minuman.">

    <title>{{ config('app.name') }} — Sewa PS4 &amp; PS5</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    {{-- ================= HERO BANNER ================= --}}
    <header class="grid-noise relative overflow-hidden">
        {{-- Glow & gradasi background --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-950/50 via-transparent to-ink-950"></div>
        <div class="pointer-events-none absolute -top-44 left-1/2 h-96 w-[40rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
        <div class="pointer-events-none absolute right-[-8rem] top-20 h-72 w-72 rounded-full bg-neon-500/10 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 pb-24 pt-8 sm:px-6">
            {{-- Nav mini --}}
            <nav class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                        <x-icon name="gamepad-2" class="h-4.5 w-4.5" />
                    </span>
                    <span class="text-sm font-extrabold uppercase tracking-widest text-white">{{ config('app.name') }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="btn-subtle">Masuk</a>
                    <a href="{{ route('register') }}" class="btn-primary">
                        <x-icon name="user-plus" class="h-4 w-4" />
                        Daftar Pelanggan
                    </a>
                </div>
            </nav>

            {{-- Konten hero --}}
            <div class="mx-auto max-w-3xl pt-16 text-center sm:pt-20">
                <span class="badge bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                    <x-icon name="zap" class="h-3.5 w-3.5" />
                    Tempat Nongkrong Gamer Sejak 2020
                </span>

                <h1 class="mt-6 text-5xl font-extrabold lowercase tracking-tight text-white sm:text-7xl">
                    rebite
                    <span class="bg-gradient-to-r from-brand-300 via-neon-400 to-brand-300 bg-clip-text text-transparent">playstation</span>
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-400 sm:text-lg">
                    Sewa konsol <strong class="font-bold text-white">PS4</strong> &
                    <strong class="font-bold text-white">PS5</strong> per jam sistem,
                    lengkap dengan paket hemat, snack, dan minuman. Pantau unit yang
                    kosong secara langsung.
                </p>

                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <a href="#units" class="btn-primary">
                        <x-icon name="layout-grid" class="h-4 w-4" />
                        Cek Unit Tersedia
                    </a>
                    <a href="{{ route('customer.order.index') }}" class="btn-primary">
                        <x-icon name="barcode" class="h-4 w-4" />
                        Pesan via Barcode
                    </a>
                    <a href="#packages" class="btn-ghost">
                        <x-icon name="tags" class="h-4 w-4" />
                        Lihat Paket Harga
                    </a>
                    <a href="{{ route('customer.display') }}" class="btn-ghost">
                        <x-icon name="monitor" class="h-4 w-4" />
                        Monitor Live TV
                    </a>
                    <a href="{{ route('booking.check') }}" class="btn-ghost">
                        <x-icon name="search" class="h-4 w-4" />
                        Cek Status Booking
                    </a>
                    <a href="{{ route('register') }}" class="btn-ghost">
                        <x-icon name="calendar-days" class="h-4 w-4" />
                        Booking Konsol Online
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-20 px-4 pb-20 sm:px-6">

        {{-- ================= AJAKAN DAFTAR (booking wajib login) ================= --}}
        <section aria-label="Booking konsol online" class="lg:-mt-10">
            <div class="card grid gap-6 p-6 ring-1 ring-inset ring-brand-500/20 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <h2 class="text-xl font-extrabold text-white sm:text-2xl">
                        Booking konsol tanpa datang duluan
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-400">
                        Daftar sekali pakai nomor WhatsApp, lalu pilih unit yang kosong beserta jam mainnya.
                        Slot langsung terkunci untuk Anda, dan status unit berubah jadi
                        <strong class="font-semibold text-emerald-300">Terisi</strong> selama Anda bermain —
                        pelanggan lain langsung melihatnya berubah.
                    </p>
                    <p class="mt-3 text-sm text-slate-500">
                        Sudah punya akun?
                        <a href="{{ route('login') }}" class="font-semibold text-brand-300 hover:text-brand-200">Masuk di sini</a>.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ route('register') }}" class="btn-primary whitespace-nowrap">
                        <x-icon name="user-plus" class="h-4 w-4" />
                        Daftar Gratis
                    </a>
                    <a href="{{ route('login') }}" class="btn-ghost whitespace-nowrap">
                        <x-icon name="log-in" class="h-4 w-4" />
                        Sudah Daftar
                    </a>
                </div>
            </div>
        </section>

        {{-- ================= RINGKASAN STATUS ================= --}}
        <section aria-label="Ringkasan ketersediaan unit" class="lg:-mt-10">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="card p-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total Unit</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-white">{{ $stats['total'] }}</p>
                </div>
                <div class="card p-5 ring-1 ring-inset ring-emerald-500/30">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-400/70">Siap Main</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-emerald-300">{{ $stats['ready'] }}</p>
                </div>
                <div class="card p-5 ring-1 ring-inset ring-rose-500/30">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-400/70">Sedang Dipakai</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-rose-300">{{ $stats['busy'] }}</p>
                </div>
                <div class="card p-5 ring-1 ring-inset ring-amber-500/30">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-400/70">Dalam Servis</p>
                    <p class="tabular mt-2 text-3xl font-extrabold text-amber-300">{{ $stats['maintenance'] }}</p>
                </div>
            </div>
        </section>

        {{-- ================= STATUS UNIT PS ================= --}}
        <section id="units" class="scroll-mt-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Ketersediaan Unit</h2>
                    <p class="mt-1 text-sm text-slate-500">Status terkini setiap konsol. Unit hijau langsung bisa disewa di kasir.</p>
                </div>
                <a href="{{ route('customer.display') }}" class="btn-subtle">
                    <x-icon name="monitor" class="h-3.5 w-3.5" />
                    Tampilan Live Monitor
                </a>
            </div>

            @if ($units->isEmpty())
                <div class="card px-6 py-16 text-center">
                    <x-icon name="gamepad-2" class="mx-auto h-10 w-10 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada unit yang terdaftar.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($units as $unit)
                        @php($indicator = $unit->status->indicator())
                        @php($session = $unit->runningSession)

                        <div class="card relative overflow-hidden p-5 transition duration-200 hover:border-white/15 {{ $indicator['ring'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ $unit->type }}</p>
                                    <h3 class="mt-1 truncate text-base font-extrabold text-white">{{ $unit->name }}</h3>
                                    <p class="tabular mt-0.5 text-xs text-slate-500">{{ $unit->code }}</p>
                                </div>
                                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $indicator['dot'] }}"></span>
                            </div>

                            <div class="mt-5 border-t border-white/5 pt-4">
                                @if ($unit->status === UnitStatus::READY)
                                    <p class="text-lg font-extrabold tracking-wide text-emerald-300">READY</p>
                                    <p class="mt-1 text-sm text-emerald-200/60">
                                        {{ $unit->isFree()
                                            ? 'Gratis · Open Play'
                                            : Money::format($unit->hourly_rate).' / jam' }}
                                    </p>
                                @elseif ($unit->status === UnitStatus::BUSY)
                                    <p class="text-lg font-extrabold tracking-wide text-rose-300">IN USE</p>
                                    @if ($session)
                                        @php($remaining = max(0, $session->remainingSeconds()))
                                        <p class="tabular mt-1 text-sm {{ $remaining <= 0 ? 'text-amber-300' : 'text-rose-200/70' }}">
                                            {{ $remaining <= 0
                                                ? 'Waktu habis · segera selesai'
                                                : 'Sisa ± '.ceil($remaining / 60).' menit · '.($session->package_name ?? 'Open Play') }}
                                        </p>
                                    @else
                                        <p class="mt-1 text-sm text-rose-200/70">Sedang dipakai pengunjung</p>
                                    @endif
                                @else
                                    <p class="text-lg font-extrabold tracking-wide text-amber-300">SERVIS</p>
                                    <p class="mt-1 text-sm text-amber-200/60">Sedang dalam perbaikan</p>
                                @endif
                            </div>

                            @if ($unit->status === UnitStatus::READY)
                                <a href="{{ route('login') }}" class="btn-primary mt-4 w-full text-xs">
                                    <x-icon name="calendar-days" class="h-3.5 w-3.5" />
                                    Booking Konsol Ini
                                </a>
                            @elseif ($unit->status === UnitStatus::BUSY)
                                <a href="{{ route('login') }}" class="btn-primary mt-4 w-full text-xs">
                                    <x-icon name="calendar-days" class="h-3.5 w-3.5" />
                                    Booking Jam Berikutnya
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ================= KATALOG PAKET HARGA ================= --}}
        <section id="packages" class="scroll-mt-8">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Paket Sewa</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih paket yang paling pas — makin lama makin hemat.</p>
            </div>

            @if ($unitTypes->isNotEmpty())
                <div class="mb-6 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tarif per jam:</span>
                    @foreach ($unitTypes as $group)
                        <span class="badge bg-ink-800 text-slate-300 ring-1 ring-inset ring-white/10">
                            {{ $group['type'] }} — {{ $group['rate_labels']->join(' / ') }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($ratePackages->isEmpty())
                <div class="card px-6 py-16 text-center">
                    <x-icon name="tags" class="mx-auto h-10 w-10 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Paket belum tersedia.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($ratePackages as $package)
                        <div class="card card-hover flex flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-extrabold text-white">{{ $package->name }}</h3>
                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                        <x-icon name="clock" class="h-3.5 w-3.5" />
                                        {{ $package->durationLabel() }}
                                    </p>
                                </div>
                                <span class="text-lg font-extrabold text-brand-300">{{ Money::format($package->price) }}</span>
                            </div>
                            @if ($package->description)
                                <p class="mt-3 text-xs leading-relaxed text-slate-500">{{ $package->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ================= MENU SNACK & MINUMAN ================= --}}
        <section id="menu" class="scroll-mt-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Snack &amp; Minuman</h2>
                    <p class="mt-1 text-sm text-slate-500">Teman begadang main sampai pagi.</p>
                </div>

                <a href="{{ route('customer.order.index') }}" class="btn-ghost">
                    <x-icon name="barcode" class="h-4 w-4" />
                    Pesan dengan Scan Barcode
                </a>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($productGroups as $group)
                    <div class="card overflow-hidden">
                        <header class="flex items-center gap-2.5 border-b border-white/5 px-5 py-4">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500/15 text-brand-300">
                                <x-icon :name="$group['category'] === ProductCategory::DRINK ? 'coffee' : 'cookie'" class="h-4 w-4" />
                            </span>
                            <h3 class="text-sm font-extrabold text-white">{{ $group['category']->label() }}</h3>
                        </header>

                        <ul class="divide-y divide-white/5">
                            @foreach ($group['items'] as $product)
                                <li class="flex items-center justify-between gap-3 px-5 py-3">
                                    <span class="text-sm text-slate-300">{{ $product->name }}</span>
                                    <span class="tabular shrink-0 text-sm font-bold text-slate-100">{{ Money::format($product->price) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    {{-- ================= FOOTER ================= --}}
    <footer class="relative border-t border-white/5">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6">
            <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500/15 text-brand-300">
                    <x-icon name="gamepad-2" class="h-4 w-4" />
                </span>
                <div>
                    <p class="text-sm font-extrabold uppercase tracking-widest text-white">{{ config('app.name') }}</p>
                    <p class="text-xs text-slate-600">Buka setiap hari · 10.00 — 23.00 WIB</p>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs text-slate-500">
                <a href="{{ route('customer.order.index') }}" class="inline-flex items-center gap-1.5 transition hover:text-white">
                    <x-icon name="barcode" class="h-3.5 w-3.5" />
                    Pesan Barcode
                </a>
                <a href="{{ route('customer.display') }}" class="inline-flex items-center gap-1.5 transition hover:text-white">
                    <x-icon name="monitor" class="h-3.5 w-3.5" />
                    Monitor Live
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 transition hover:text-white">
                    <x-icon name="lock" class="h-3.5 w-3.5" />
                    Area Kasir
                </a>
            </div>
        </div>
    </footer>
</body>
</html>