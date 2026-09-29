<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Masuk') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid-noise min-h-screen bg-ink-950">
    <div class="relative flex min-h-screen items-center justify-center px-4 py-10">

        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-40 -top-40 h-96 w-96 rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-neon-600/15 blur-3xl"></div>
        </div>

        <div class="relative w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-neon-500 text-white shadow-xl shadow-brand-500/30">
                    <x-icon name="zap" class="h-7 w-7" />
                </span>

                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-white">Rebite Playstation</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Sistem Pemantauan Unit Rental Konsol &amp; Rekonsiliasi Kasir
                </p>
            </div>

            <div class="card p-6 sm:p-8">
                @include('layouts.partials.flash')

                @yield('content')
            </div>
        </div>
    </div>

    <x-toast />
</body>
</html>
