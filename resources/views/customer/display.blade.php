<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">

    <title>Monitor Live — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-screen min-h-screen flex-col overflow-hidden bg-ink-950 text-slate-200 antialiased">
    <div
        x-cloak
        x-data="liveDisplay({
            units: @js($payload['units']),
            serverTimestamp: @js($payload['server_timestamp']),
            serverTime: @js($payload['server_time']),
            apiUrl: @js(route('customer.display.data')),
            pollInterval: 10000,
        })"
        class="flex h-full flex-col"
        x-on:keydown.f5.window="poll()"
    >

        {{-- ================= HEADER ================= --}}
        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-white/5 bg-ink-900/70 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                    <x-icon name="gamepad-2" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-extrabold uppercase tracking-widest text-white">{{ config('app.name') }}</p>
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500">Live Monitor Unit</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="badge bg-rose-500/15 text-rose-300 ring-1 ring-inset ring-rose-500/30">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-rose-400"></span>
                    LIVE
                </span>
            </div>

            <div class="flex items-center gap-6">
                <div class="text-right">
                    <p x-data="clock" class="tabular text-xl font-bold text-white" x-text="time"></p>
                    <p class="text-[10px] uppercase tracking-widest text-slate-600">Jam Lokal</p>
                </div>
                <div class="text-right">
                    <p class="tabular text-xl font-bold text-slate-300" x-text="lastSyncLabel()"></p>
                    <p class="text-[10px] uppercase tracking-widest text-slate-600">Update Terakhir</p>
                </div>
            </div>
        </header>

        {{-- ================= GRID UNIT ================= --}}
        <main class="grid-noise flex-1 overflow-y-auto p-4 sm:p-6">
            <div
                x-cloak
                x-show="units.length === 0"
                class="grid h-full place-items-center"
            >
                <div class="card px-8 py-16 text-center">
                    <x-icon name="gamepad-2" class="mx-auto h-12 w-12 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada unit untuk ditampilkan.</p>
                    <p class="mt-1 text-xs text-slate-600">Screen ini menyesuaikan sendiri ketika unit ditambahkan.</p>
                </div>
            </div>

            <div
                x-cloak
                x-show="units.length > 0"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
            >
                <template x-for="unit in units" :key="unit.id">
                    <div
                        class="relative flex aspect-[16/10] flex-col justify-between overflow-hidden rounded-2xl border p-5 transition duration-300"
                        x-bind:class="cardClasses(unit)"
                    >
                        {{-- Indikator status --}}
                        <span
                            class="absolute right-4 top-4 h-3 w-3 rounded-full"
                            x-bind:class="dotClass(unit)"
                        ></span>

                        {{-- Identitas unit --}}
                        <div class="min-w-0 pr-8">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500" x-text="unit.type"></p>
                            <p class="mt-1 truncate text-xl font-extrabold uppercase tracking-wide text-white" x-text="unit.code"></p>
                            <p class="truncate text-xs text-slate-500" x-text="unit.name"></p>
                        </div>

                        {{-- Isi sesuai status --}}
                        <div class="space-y-1">
                            <template x-if="unit.status === 'READY'">
                                <div>
                                    <p class="text-3xl font-extrabold tracking-wide text-emerald-300">READY</p>
                                    <p class="mt-1 text-xs text-emerald-200/60" x-text="unit.hourly_rate_label + ' / jam'"></p>
                                </div>
                            </template>

                            <template x-if="unit.status === 'BUSY' && unit.session">
                                <div>
                                    <p
                                        class="timer-digits text-4xl"
                                        x-bind:class="unit.session.is_time_up ? 'text-amber-300' : 'text-rose-300'"
                                        x-text="clock(unit.session.remaining_seconds)"
                                    ></p>
                                    <p
                                        class="mt-1 truncate text-xs font-semibold"
                                        x-bind:class="unit.session.is_time_up ? 'text-amber-200/80' : 'text-rose-200/60'"
                                        x-text="(unit.session.is_time_up ? 'WAKTU HABIS · ' : '') + (unit.session.package_name || 'Open Play')"
                                    ></p>
                                </div>
                            </template>

                            <template x-if="unit.status === 'MAINTENANCE'">
                                <div>
                                    <p class="text-3xl font-extrabold tracking-wide text-amber-300">SERVIS</p>
                                    <p class="mt-1 text-xs text-amber-200/60">Unit tidak tersedia</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</body>
</html>