<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950">
    <div x-data="cashierLayout({ initial: {{ $pendingNavBookingsCount }}, url: @js(auth()->user()?->isCashier() ? route('pos.bookings.pending-count') : null) })" x-on:keydown.escape.window="sidebar = false" class="flex min-h-screen">

        {{-- ================= SIDEBAR (DESKTOP) ================= --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 shrink-0 flex-col border-r border-white/5 bg-ink-900 lg:flex">
            @include('layouts.partials.brand')

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4 pt-2">
                @include('layouts.partials.nav')
            </nav>

            @include('layouts.partials.sidebar-footer')
        </aside>

        {{-- ================= SIDEBAR (MOBILE) ================= --}}
        <div
            x-cloak
            x-show="sidebar"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden"
            x-on:click="sidebar = false"
        ></div>

        <aside
            x-cloak
            x-show="sidebar"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-white/5 bg-ink-900 lg:hidden"
        >
            @include('layouts.partials.brand')

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4 pt-2">
                @include('layouts.partials.nav')
            </nav>

            @include('layouts.partials.sidebar-footer')
        </aside>

        {{-- ================= MAIN ================= --}}
        <div class="flex min-w-0 flex-1 flex-col lg:pl-64">

            @include('layouts.partials.topbar')

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-[1600px]">
                    @include('layouts.partials.flash')

                    @yield('content')
                </div>
            </main>

            <footer class="border-t border-white/5 px-6 py-4 lg:px-8">
                <p class="text-center text-xs text-slate-600">
                    {{ config('app.name') }} — Sistem Informasi Pemantauan Unit Rental Konsol &amp; Rekonsiliasi Kasir
                </p>
            </footer>
        </div>
    </div>

    <x-toast />
</body>
</html>
