@use('App\Enums\RentalRequestStatus', 'RentalRequestStatus')
@use('App\Enums\UnitStatus', 'UnitStatus')
@use('App\Support\Money', 'Money')

<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Permintaan Sewa Saya — {{ config('app.name') }}</title>

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
                    <p class="text-[11px] text-slate-500">Permintaan Sewa</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden text-xs text-slate-500 sm:block">
                    Halo, <strong class="font-semibold text-white">{{ auth()->user()->name }}</strong>
                    <span class="tabular text-slate-600">· {{ auth()->user()->phone }}</span>
                </span>

                <a href="{{ route('customer.dashboard') }}" class="btn-subtle">
                    <x-icon name="home" class="h-3.5 w-3.5" />
                    Beranda
                </a>

                <a href="{{ route('account.settings') }}" class="btn-subtle">
                    <x-icon name="settings-2" class="h-3.5 w-3.5" />
                    Pengaturan Akun
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

    <main class="mx-auto max-w-6xl space-y-8 px-4 py-10 sm:px-6">
        @include('layouts.partials.flash')

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white">Permintaan Sewa</h1>
                <p class="mt-1 text-sm text-slate-400">Ajukan sewa Playbox/unit sesuai jadwal yang Anda inginkan. Permintaan akan dikonfirmasi oleh kasir terlebih dahulu.</p>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-[1fr,380px]">
            <div class="space-y-8">
                <div class="card p-6">
                    <h2 class="text-lg font-extrabold text-white">Ajukan Permintaan Sewa</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih unit dan jam sewa. Durasi minimal 1 jam.</p>

                    <form method="POST" action="{{ route('customer.rentals.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                        @csrf

                        <div class="sm:col-span-2">
                            <label for="unit_id" class="label">Pilih Unit</label>
                            <select id="unit_id" name="unit_id" class="input">
                                <option value="">-- Pilih Unit --</option>
                                @foreach ($units as $unit)
                                    @php
                                        $hint = match (true) {
                                            $unit['status'] === 'BUSY' => 'Terpakai',
                                            $unit['is_reserved'] => 'Dipesan: '.$unit['reservation_label'],
                                            default => 'Tersedia',
                                        };
                                    @endphp
                                    <option value="{{ $unit['id'] }}" @selected(old('unit_id') == $unit['id'])>
                                        {{ $unit['name'] }} ({{ $unit['code'] }}) - {{ $hint }}
                                    </option>
                                @endforeach
                            </select>
                            @error('unit_id')
                                <p class="mt-2 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="start_time" class="label">Waktu Mulai</label>
                            <input type="datetime-local" id="start_time" name="start_time" value="{{ old('start_time') }}" class="input">
                            @error('start_time')
                                <p class="mt-2 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="end_time" class="label">Waktu Selesai</label>
                            <input type="datetime-local" id="end_time" name="end_time" value="{{ old('end_time') }}" class="input">
                            @error('end_time')
                                <p class="mt-2 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="package_id" class="label">Paket (Opsional)</label>
                            <select id="package_id" name="package_id" class="input">
                                <option value="">Tanpa Paket</option>
                                @foreach ($ratePackages as $package)
                                    <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>
                                        {{ $package->name }} - {{ $package->durationLabel() }} ({{ Money::format($package->price) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('package_id')
                                <p class="mt-2 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="notes" class="label">Catatan (Opsional)</label>
                            <textarea id="notes" name="notes" rows="3" class="input resize-none" placeholder="Tambahkan keterangan jika ada">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-2 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <button type="submit" class="btn-primary">
                                <x-icon name="send" class="h-4 w-4" />
                                Kirim Permintaan Sewa
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card overflow-hidden">
                    <header class="border-b border-white/5 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-extrabold text-white">Riwayat Permintaan Sewa</h2>
                            <p class="mt-1 text-sm text-slate-500">Daftar permintaan sewa yang Anda ajukan</p>
                        </div>
                    </header>

                    @if ($rentalRequests->isEmpty())
                        <div class="px-6 py-12 text-center">
                            <x-icon name="calendar-days" class="mx-auto h-10 w-10 text-slate-700" />
                            <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada permintaan sewa.</p>
                        </div>
                    @else
                        <div class="divide-y divide-white/5">
                            @foreach ($rentalRequests as $req)
                                <article class="flex flex-wrap items-start justify-between gap-4 px-6 py-5">
                                    <div class="min-w-0 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-extrabold text-white">{{ $req->rentalRequestCode() }}</h3>
                                            <span class="badge {{ $req->status->badgeClass() }}">{{ $req->status->label() }}</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-400">
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-icon name="gamepad-2" class="h-4 w-4" />
                                                {{ $req->unit?->name }} ({{ $req->unit?->code }})
                                            </span>
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-icon name="clock" class="h-4 w-4" />
                                                {{ $req->start_time->format('d M Y H:i') }} → {{ $req->end_time->format('d M Y H:i') }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-icon name="tags" class="h-4 w-4" />
                                                {{ $req->package_name ?? 'Tanpa Paket' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-icon name="receipt" class="h-4 w-4" />
                                                {{ Money::format($req->total_price) }}
                                            </span>
                                        </div>
                                        @if ($req->notes)
                                            <p class="text-sm text-slate-500">Catatan: {{ $req->notes }}</p>
                                        @endif
                                        <p class="text-xs text-slate-600">Diajukan {{ $req->created_at->diffForHumans() }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div class="border-t border-white/5 px-6 py-4">
                            {{ $rentalRequests->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <aside class="space-y-4">
                <div class="card p-6">
                    <h3 class="text-sm font-extrabold uppercase tracking-widest text-slate-400">Keterangan Status</h3>
                    <ul class="mt-4 space-y-2 text-sm text-slate-400">
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            Menunggu Konfirmasi - Kasir perlu approve permintaan Anda
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            Terkonfirmasi - Permintaan disetujui, menunggu proses sesuai kebijakan
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                            Dibatalkan - Permintaan tidak disetujui/dibatalkan
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                            Selesai - Permintaan telah diproses
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

    <x-toast />
</body>
</html>