<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>QR Meja — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Halaman berdiri sendiri (tidak extends layout internal) supaya
           hasil cetak hanya berisi kartu QR: tanpa sidebar, topbar, footer. */
        @media print {
            @page { margin: 8mm; }
            body { background: #ffffff !important; }
            #qr-controls { display: none !important; }
            #qr-sheet { gap: 4mm !important; }
            .qr-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">

        {{-- ================= KONTROL (tidak ikut tercetak) ================= --}}
        <div id="qr-controls" class="mb-6 space-y-4">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-extrabold text-white">QR Meja</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Cetak lalu tempel di meja. Pelanggan memindai QR ini untuk pesan sendiri.
                        Menu hanya terbuka saat unit sedang disewa.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('owner.units.index') }}" class="btn-ghost">
                        <x-icon name="arrow-left-right" class="h-4 w-4" />
                        Kembali
                    </a>

                    <button type="button" class="btn-primary" x-on:click="window.print()">
                        <x-icon name="printer" class="h-4 w-4" />
                        Cetak
                    </button>
                </div>
            </header>

            <form method="GET" action="{{ route('owner.units.qr') }}"
                  class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="qr-q" class="label">Cari</label>
                    <input id="qr-q" name="q" type="text" class="input"
                           value="{{ $filters['q'] ?? '' }}" placeholder="Kode atau nama unit">
                </div>

                <div>
                    <label for="qr-type" class="label">Tipe</label>
                    <select id="qr-type" name="type" class="input">
                        <option value="">Semua</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn-subtle">Terapkan</button>
            </form>

            <p class="text-xs text-slate-600">
                Menampilkan <span class="font-bold text-slate-400">{{ $cards->count() }}</span> unit.
            </p>
        </div>

        {{-- ================= SHEET KARTU ================= --}}
        @if ($cards->isEmpty())
            <div class="card px-6 py-16 text-center">
                <x-icon name="circle-alert" class="mx-auto h-10 w-10 text-slate-700" />
                <p class="mt-4 text-sm font-semibold text-slate-400">Tidak ada unit yang cocok dengan filter.</p>
            </div>
        @else
            <div id="qr-sheet" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($cards as $card)
                    <div class="qr-card flex flex-col items-center rounded-xl border border-slate-300 bg-white p-3 text-center">
                        <p class="text-base font-extrabold text-slate-900">{{ $card['unit']->code }}</p>
                        <p class="line-clamp-1 text-[10px] font-semibold text-slate-500">{{ $card['unit']->name }}</p>

                        {{-- QR dirender server-side jadi tetap tajam saat dicetak. --}}
                        <div class="my-2 w-full [&>svg]:mx-auto [&>svg]:h-auto [&>svg]:w-full [&>svg]:max-w-[220px]">
                            {!! $card['svg'] !!}
                        </div>

                        <p class="text-[9px] text-slate-400">Pindai untuk pesan</p>
                        <p class="mt-0.5 break-all font-mono text-[9px] text-slate-500">{{ $card['url'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
