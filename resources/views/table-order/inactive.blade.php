@php
    $qrUrl = \App\Support\TableQr::urlFor($unit->code);
@endphp

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="description" content="Pemesanan mandiri unit {{ $unit->code }} — {{ config('app.name') }}">

    <title>Unit sedang tidak aktif — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        <div class="card p-6 text-center sm:p-8">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30">
                <x-icon name="triangle-alert" class="h-7 w-7" />
            </span>

            <h1 class="mt-5 text-xl font-extrabold text-white">Unit sedang tidak aktif</h1>

            <p class="mt-3 text-sm leading-relaxed text-slate-400">
                Unit sedang tidak aktif. Silakan hubungi kasir untuk memulai main.
            </p>

            <div class="mt-5 rounded-xl border border-white/5 bg-ink-900 px-4 py-3 text-left">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Unit ini</p>
                <p class="mt-1 text-sm font-bold text-white">{{ $unit->code }} &middot; {{ $unit->name }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $unit->type }}</p>
            </div>

            <p class="mt-5 text-xs text-slate-600">
                Tekan muat ulang setelah kasir memulai sesi sewa.
            </p>

            <button type="button" class="btn-primary mt-4 w-full" x-on:click="window.location.reload()">
                <x-icon name="refresh-cw" class="h-4 w-4" />
                Muat ulang
            </button>
        </div>

        <p class="mt-6 text-center text-[11px] text-slate-700">
            <a href="{{ $qrUrl }}" class="hover:text-slate-500">{{ $qrUrl }}</a>
        </p>
    </main>

</body>
</html>
