@extends('layouts.app')

@section('title', 'Grid Monitoring Unit')
@section('page-title', 'Grid Monitoring Unit')
@section('page-subtitle', 'Pantau status & waktu sewa setiap konsol secara real-time')

@section('content')
    <div
        x-data="unitGrid({
            apiIndex: @js(route('api.units.index')),
            apiMeta: @js(route('api.units.meta', ['unit' => '__ID__'])),
            apiStore: @js(route('api.sessions.store')),
            apiSession: (id) => @js(route('api.sessions.show', ['rentalSession' => '__ID__'])).replace('__ID__', id),
            csrfToken: @js(csrf_token()),
            pollInterval: 5000,
        })"
        class="space-y-5"
    >
        {{-- ================= TOOLBAR ================= --}}
        <div class="card flex flex-wrap items-center gap-3 p-4">
            <div class="relative min-w-[220px] flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                    <x-icon name="search" class="h-4 w-4" />
                </span>

                <input
                    type="search"
                    x-model.debounce.300ms="query"
                    placeholder="Cari unit (nama atau kode)…"
                    class="input pl-10"
                >
            </div>

            <div class="flex items-center gap-1 rounded-xl bg-ink-900 p-1">
                @foreach ([
                    ['key' => 'ALL', 'label' => 'Semua', 'icon' => 'layout-grid'],
                    ['key' => 'READY', 'label' => 'Ready', 'icon' => 'play'],
                    ['key' => 'BUSY', 'label' => 'Main', 'icon' => 'clock'],
                    ['key' => 'MAINTENANCE', 'label' => 'Servis', 'icon' => 'wrench'],
                ] as $tab)
                    <button
                        type="button"
                        x-on:click="filter = '{{ $tab['key'] }}'"
                        x-bind:class="filter === '{{ $tab['key'] }}'
                            ? 'bg-brand-500 text-white'
                            : 'text-slate-400 hover:bg-white/5 hover:text-white'"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition"
                    >
                        <x-icon :name="$tab['icon']" class="h-3.5 w-3.5" />
                        {{ $tab['label'] }}
                        <span x-text="counts['{{ $tab['key'] }}']" class="tabular opacity-70"></span>
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <span
                    x-show="counts.TIME_UP > 0"
                    class="badge bg-amber-500/20 text-amber-200 ring-1 ring-inset ring-amber-500/40"
                >
                    <x-icon name="bell-ring" class="h-3.5 w-3.5" />
                    <span x-text="counts.TIME_UP + ' waktu habis'"></span>
                </span>

                <button
                    type="button"
                    x-on:click="$store.sound.toggle()"
                    class="grid h-10 w-10 place-items-center rounded-xl text-slate-500 transition hover:bg-white/5 hover:text-slate-200"
                    :title="$store.sound.enabled ? 'Matikan alarm' : 'Aktifkan alarm'"
                >
                    <i x-show="$store.sound.enabled" data-lucide="volume-2" class="h-4 w-4"></i>
                    <i x-cloak x-show="! $store.sound.enabled" data-lucide="volume-x" class="h-4 w-4"></i>
                </button>

                <button
                    type="button"
                    x-on:click="refresh()"
                    class="grid h-10 w-10 place-items-center rounded-xl text-slate-500 transition hover:bg-white/5 hover:text-slate-200"
                    title="Sinkronkan sekarang"
                >
                    <i data-lucide="refresh-cw" class="h-4 w-4" x-bind:class="loading ? 'animate-spin' : ''"></i>
                </button>
            </div>
        </div>

        {{-- ================= RINGKASAN SHIFT ================= --}}
        @if ($activeShift)
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="card flex items-center gap-3 p-3.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-sky-500/15 text-sky-300">
                        <x-icon name="clock" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Shift Mulai</p>
                        <p class="tabular truncate text-sm font-bold text-white">
                            {{ $activeShift->start_time->format('H:i') }} WIB
                        </p>
                    </div>
                </div>

                <div class="card flex items-center gap-3 p-3.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-500/15 text-emerald-300">
                        <x-icon name="banknote" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Modal Awal</p>
                        <p class="tabular truncate text-sm font-bold text-white">
                            {{ \App\Support\Money::format($activeShift->starting_cash) }}
                        </p>
                    </div>
                </div>

                <div class="card flex items-center gap-3 p-3.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-rose-500/15 text-rose-300">
                        <x-icon name="play" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Sedang Main</p>
                        <p class="tabular text-sm font-bold text-white">
                            <span x-text="counts.BUSY"></span> unit
                        </p>
                    </div>
                </div>

                <a href="{{ route('shift.end') }}" class="card card-hover flex items-center gap-3 p-3.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-500/15 text-amber-300">
                        <x-icon name="wallet" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Aksi</p>
                        <p class="truncate text-sm font-bold text-amber-300">Tutup Shift</p>
                    </div>
                </a>
            </div>
        @endif

        {{-- ================= GRID CARD ================= --}}
        <div x-show="loading" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            @for ($i = 0; $i < 10; $i++)
                <div class="card h-40 animate-pulse bg-ink-800/50"></div>
            @endfor
        </div>

        <div
            x-cloak
            x-show="!loading && visibleUnits.length === 0"
            class="card px-6 py-20 text-center"
        >
            <x-icon name="search" class="mx-auto h-10 w-10 text-slate-700" />
            <p class="mt-4 text-sm font-semibold text-slate-400">Tidak ada unit yang cocok.</p>
            <button type="button" x-on:click="query = ''; filter = 'ALL'" class="btn-subtle mt-3 text-xs">
                Reset filter
            </button>
        </div>

        <div x-cloak x-show="!loading" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            <template x-for="unit in visibleUnits" :key="unit.id">
                <button
                    type="button"
                    x-on:click="openCard(unit)"
                    x-bind:class="statusClasses(unit)"
                    class="group relative flex aspect-[4/3] flex-col justify-between overflow-hidden rounded-2xl border p-4 text-left transition duration-200 hover:scale-[1.02] hover:shadow-2xl focus:outline-none focus:ring-2 focus:ring-white/30"
                >
                    {{-- Indikator warna status --}}
                    <span
                        class="absolute right-3 top-3 h-2.5 w-2.5 rounded-full"
                        x-bind:class="[dotClass(unit), { 'animate-pulse-ring': unit.session && ! unit.session.is_time_up }]"
                    ></span>

                    {{-- Judul --}}
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500" x-text="unit.type"></p>
                        <p class="mt-1 truncate text-base font-extrabold text-white" x-text="unit.name"></p>
                        <p class="tabular mt-0.5 text-xs text-slate-500" x-text="unit.code"></p>
                    </div>

                    {{-- Isi sesuai status --}}
                    <div>
                        {{-- SIAP (HIJAU) --}}
                        <template x-if="unit.status === 'READY'">
                            <div>
                                <p class="text-2xl font-extrabold text-emerald-300" x-text="unit.hourly_rate_label"></p>
                                <p class="text-xs text-emerald-400/70">per jam · klik untuk mulai sewa</p>
                            </div>
                        </template>

                        {{-- BERJALAN (MERAH) --}}
                        <template x-if="unit.status === 'BUSY' && unit.session">
                            <div>
                                <p
                                    class="timer-digits"
                                    x-bind:class="unit.session.is_time_up ? 'text-amber-300' : 'text-rose-300'"
                                    x-text="formatClock(unit)"
                                ></p>

                                <p class="mt-1 flex items-center gap-1.5 text-xs"
                                   x-bind:class="unit.session.is_time_up ? 'text-amber-200/80' : 'text-rose-200/60'">
                                    <i x-show="unit.session.is_time_up" data-lucide="triangle-alert" class="h-3.5 w-3.5"></i>
                                    <i x-show="! unit.session.is_time_up" data-lucide="clock" class="h-3.5 w-3.5"></i>
                                    <span x-text="unit.session.is_time_up
                                        ? 'WAKTU HABIS'
                                        : 'dari ' + unit.session.planned_minutes + ' menit'"></span>
                                </p>
                            </div>
                        </template>

                        {{-- SERVIS (KUNING) --}}
                        <template x-if="unit.status === 'MAINTENANCE'">
                            <div>
                                <p class="flex items-center gap-2 text-sm font-bold text-amber-300">
                                    <x-icon name="wrench" class="h-4 w-4" />
                                    Servis
                                </p>
                                <p class="mt-1 text-xs text-amber-200/60">Unit tidak dapat disewa</p>
                            </div>
                        </template>
                    </div>
                </button>
            </template>
        </div>

        {{-- ================= MODAL: MULAI SEWA (HIJAU) ================= --}}
        <div
            x-cloak
            x-show="modal === 'start'"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/75 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-on:keydown.escape.window="closeModal()"
        >
            <div
                x-show="modal === 'start'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-white/10 bg-ink-850 shadow-2xl sm:rounded-2xl"
                x-on:click.outside="closeModal()"
            >
                <header class="flex items-center gap-3 border-b border-white/5 px-5 py-4">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500/15 text-emerald-300">
                        <x-icon name="play" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-bold text-white" x-text="activeUnit?.name"></h3>
                        <p class="tabular text-xs text-slate-500">
                            <span x-text="activeUnit?.code"></span> ·
                            <span x-text="activeUnit?.hourly_rate_label"></span> / jam
                        </p>
                    </div>

                    <button type="button" x-on:click="closeModal()" class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-white/5 hover:text-white">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto p-5">
                    <div>
                        <p class="label">Pilih Paket Jam</p>

                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                            <template x-for="pkg in meta.rate_packages" :key="pkg.id">
                                <button
                                    type="button"
                                    x-on:click="selectPackage(pkg)"
                                    x-bind:class="form.rate_package_id === pkg.id && ! form.is_free_play
                                        ? 'border-brand-500 bg-brand-500/15 ring-1 ring-inset ring-brand-500/40'
                                        : 'border-white/10 bg-ink-900 hover:border-white/25'"
                                    class="rounded-xl border p-3 text-left transition"
                                >
                                    <p class="truncate text-xs font-bold text-white" x-text="pkg.name"></p>
                                    <p class="mt-0.5 text-[10px] text-slate-500" x-text="pkg.duration_label"></p>
                                    <p class="tabular mt-1.5 text-sm font-extrabold text-brand-300" x-text="pkg.price_label"></p>
                                </button>
                            </template>

                            <template x-if="metaLoading">
                                <div class="col-span-full rounded-xl border border-white/5 bg-ink-900 p-4 text-center text-xs text-slate-500">
                                    Memuat paket…
                                </div>
                            </template>
                        </div>

                        <p x-show="form.rate_package_id && errors.rate_package_id" class="mt-1.5 text-xs text-rose-400"
                           x-text="errors.rate_package_id?.[0]"></p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-ink-900 p-4">
                        <button type="button" x-on:click="selectFreePlay()"
                                x-bind:class="form.is_free_play ? 'border-emerald-500/60 bg-emerald-500/10' : 'border-white/5'"
                                class="flex w-full items-center gap-3 rounded-lg border p-3 text-left transition">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-500/15 text-emerald-300">
                                <x-icon name="sparkles" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="text-xs font-bold text-white">Open Play</p>
                                <p class="text-[10px] text-slate-500">Tanpa batas paket — tarif dihitung per jam dari unit</p>
                            </div>
                        </button>

                        <div x-show="form.is_free_play" x-transition class="mt-3">
                            <label for="open_play_minutes" class="label">Durasi Open Play (menit)</label>
                            <input
                                id="open_play_minutes"
                                type="number"
                                min="5"
                                max="1440"
                                step="5"
                                x-model.number="form.open_play_minutes"
                                class="input tabular"
                            >
                            <p class="mt-1.5 text-xs text-slate-500">
                                Estimasi:
                                <strong class="text-emerald-300" x-text="window.Rebite.rupiah(estimatedFee)"></strong>
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-brand-500/25 bg-brand-500/[0.07] p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Estimasi Biaya Sewa</span>
                            <span class="tabular text-xl font-extrabold text-white" x-text="window.Rebite.rupiah(estimatedFee)"></span>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-500">
                            F&B bisa ditambahkan setelah sewa berjalan dari modal detail unit.
                        </p>
                    </div>
                </div>

                <footer class="flex gap-3 border-t border-white/5 px-5 py-4">
                    <button type="button" x-on:click="closeModal()" class="btn-ghost flex-1">Batal</button>
                    <button
                        type="button"
                        x-on:click="startRental()"
                        x-bind:disabled="submitting"
                        class="btn-success flex-1"
                    >
                        <i x-show="submitting" data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                        <i x-show="! submitting" data-lucide="play" class="h-4 w-4"></i>
                        Mulai Sewa
                    </button>
                </footer>
            </div>
        </div>

        {{-- ================= MODAL: DETAIL SEWA (MERAH) ================= --}}
        <div
            x-cloak
            x-show="modal === 'detail'"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/75 p-0 backdrop-blur-sm sm:items-center sm:p-4"
        >
            <div
                x-show="modal === 'detail'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-2xl border border-white/10 bg-ink-850 shadow-2xl sm:rounded-2xl"
                x-on:click.outside="closeModal()"
            >
                <header class="flex items-center gap-3 border-b border-white/5 px-5 py-4">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/15 text-rose-300">
                        <x-icon name="clock" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-bold text-white" x-text="detail?.unit?.name"></h3>
                        <p class="tabular truncate text-xs text-slate-500">
                            <span x-text="detail?.unit?.code"></span> ·
                            <span x-text="detail?.session?.package_name"></span> ·
                            kasir <span x-text="detail?.session?.cashier"></span>
                        </p>
                    </div>

                    <div class="text-right">
                        <p
                            class="timer-digits"
                            x-bind:class="detail?.session?.is_time_up ? 'text-amber-300' : 'text-rose-300'"
                            x-text="formatClock(detail)"
                        ></p>
                        <p class="text-[10px] uppercase tracking-widest text-slate-600">sisa waktu</p>
                    </div>

                    <button type="button" x-on:click="closeModal()" class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-white/5 hover:text-white">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </header>

                <div class="grid flex-1 gap-5 overflow-y-auto p-5 lg:grid-cols-2">
                    {{-- ============ KOLOM KIRI: PESANAN F&B ============ --}}
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Pesanan F&B</h4>
                            <span class="badge bg-white/5 text-slate-400" x-text="(detail?.session?.items?.length || 0) + ' item'"></span>
                        </div>

                        <div class="space-y-2">
                            <template x-for="item in (detail?.session?.items || [])" :key="item.id">
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-white/5 bg-ink-900 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-slate-200" x-text="item.product_name"></p>
                                        <p class="tabular text-[10px] text-slate-500">
                                            <span x-text="item.qty"></span> × <span x-text="item.price_label"></span>
                                        </p>
                                    </div>
                                    <span class="tabular shrink-0 text-xs font-bold text-white" x-text="item.subtotal_label"></span>
                                </div>
                            </template>

                            <div x-show="(detail?.session?.items?.length || 0) === 0"
                                 class="rounded-lg border border-dashed border-white/10 px-3 py-6 text-center text-xs text-slate-600">
                                Belum ada pesanan.
                            </div>
                        </div>

                        <form class="space-y-2.5 rounded-xl border border-white/5 bg-ink-900 p-3.5" x-on:submit.prevent="addItem()">
                            <label for="product_id" class="label">Tambah Produk</label>

                            <select id="product_id" x-model="form.product_id" class="input">
                                <option value="">— Pilih produk —</option>
                                <template x-for="product in meta.products" :key="product.id">
                                    <option
                                        :value="product.id"
                                        x-bind:disabled="product.stock === 0"
                                        x-text="product.name + ' — ' + product.price_label + ' (sisa ' + product.stock + ')'"
                                    ></option>
                                </template>
                            </select>

                            <p x-show="errors.product_id" class="text-xs text-rose-400" x-text="errors.product_id?.[0]"></p>

                            <div class="flex gap-2">
                                <input
                                    type="number"
                                    min="1"
                                    max="50"
                                    x-model.number="form.qty"
                                    class="input tabular w-24"
                                    aria-label="Jumlah"
                                >

                                <button type="submit" x-bind:disabled="submitting" class="btn-ghost flex-1">
                                    <x-icon name="plus" class="h-4 w-4" />
                                    Tambah
                                </button>
                            </div>
                        </form>

                        <button
                            type="button"
                            x-on:click="cancelRental()"
                            x-bind:disabled="submitting"
                            class="btn-subtle w-full text-xs text-rose-400/70 hover:text-rose-300"
                        >
                            <x-icon name="x-circle" class="h-3.5 w-3.5" />
                            Batalkan Sewa (stok dikembalikan)
                        </button>
                    </div>

                    {{-- ============ KOLOM KANAN: DURASI & BAYAR ============ --}}
                    <div class="space-y-4">
                        <div class="rounded-xl border border-white/5 bg-ink-900 p-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Tambah Durasi / Extra Time</h4>

                            <div class="mt-3 flex gap-2">
                                <input
                                    type="number"
                                    min="5"
                                    max="1440"
                                    step="5"
                                    x-model.number="form.extra_minutes"
                                    class="input tabular"
                                    aria-label="Menit tambahan"
                                >

                                <button
                                    type="button"
                                    x-on:click="extendTime()"
                                    x-bind:disabled="submitting"
                                    class="btn-ghost shrink-0"
                                >
                                    <x-icon name="plus" class="h-4 w-4" />
                                    Tambah
                                </button>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <template x-for="m in [15, 30, 60, 90]" :key="m">
                                    <button type="button" x-on:click="form.extra_minutes = m"
                                            class="rounded-lg border border-white/10 px-2.5 py-1 text-[11px] font-semibold text-slate-400 transition hover:border-white/25 hover:text-white">
                                        +<span x-text="m"></span> mnt
                                    </button>
                                </template>
                            </div>

                            <p x-show="errors.extra_minutes" class="mt-1.5 text-xs text-rose-400" x-text="errors.extra_minutes?.[0]"></p>
                        </div>

                        <div class="rounded-xl border border-brand-500/25 bg-brand-500/[0.07] p-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Rincian Tagihan</h4>

                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-400">Biaya sewa</dt>
                                    <dd class="tabular font-semibold text-slate-200" x-text="detail?.session?.rental_fee_label"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-400">F&B</dt>
                                    <dd class="tabular font-semibold text-slate-200" x-text="detail?.session?.items_total_label"></dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-white/10 pt-2">
                                    <dt class="font-bold text-white">Total</dt>
                                    <dd class="tabular text-xl font-extrabold text-white" x-text="detail?.session?.grand_total_label"></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-white/5 bg-ink-900 p-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Metode Pembayaran</h4>

                            <div class="mt-3 grid grid-cols-2 gap-2.5">
                                <template x-for="method in [{ v: 'CASH', l: 'Tunai', i: 'banknote' }, { v: 'QRIS', l: 'QRIS', i: 'qr-code' }]" :key="method.v">
                                    <button
                                        type="button"
                                        x-on:click="form.payment_method = method.v"
                                        x-bind:class="form.payment_method === method.v
                                            ? 'border-brand-500 bg-brand-500/15 ring-1 ring-inset ring-brand-500/40'
                                            : 'border-white/10 hover:border-white/25'"
                                        class="flex items-center justify-center gap-2 rounded-xl border py-3 text-xs font-bold text-white transition"
                                    >
                                        <i x-bind:data-lucide="method.i" class="h-4 w-4"></i>
                                        <span x-text="method.l"></span>
                                    </button>
                                </template>
                            </div>

                            <p x-show="errors.payment_method" class="mt-1.5 text-xs text-rose-400" x-text="errors.payment_method?.[0]"></p>
                        </div>
                    </div>
                </div>

                <footer class="flex flex-wrap gap-3 border-t border-white/5 px-5 py-4">
                    <button type="button" x-on:click="closeModal()" class="btn-ghost flex-1">Tutup</button>

                    <button
                        type="button"
                        x-on:click="completeRental()"
                        x-bind:disabled="submitting"
                        class="btn-success flex-1"
                    >
                        <i x-show="submitting" data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                        <i x-show="! submitting" data-lucide="check" class="h-4 w-4"></i>
                        Selesaikan Sewa &amp; Cetak Nota
                    </button>
                </footer>
            </div>
        </div>

        {{-- ================= MODAL: NOTA / STRUK ================= --}}
        <div
            x-cloak
            x-show="modal === 'receipt'"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
        >
            <div
                x-show="modal === 'receipt'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 bg-ink-850 shadow-2xl"
            >
                <div class="border-b border-dashed border-white/10 px-5 py-4 text-center">
                    <p class="text-base font-extrabold tracking-tight text-white">REBITE PLAYSTATION</p>
                    <p class="mt-0.5 text-[10px] text-slate-500">Nota penyewaan konsol</p>
                </div>

                <div class="space-y-3 px-5 py-4 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Unit</span>
                        <span class="font-semibold text-slate-200" x-text="receipt?.unit?.name + ' (' + receipt?.unit?.code + ')'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Paket</span>
                        <span class="font-semibold text-slate-200" x-text="receipt?.package_name"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Mulai</span>
                        <span class="tabular font-semibold text-slate-200" x-text="new Date(receipt.start_time).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Durasi</span>
                        <span class="tabular font-semibold text-slate-200" x-text="receipt?.duration_minutes + ' menit'"></span>
                    </div>

                    <div class="space-y-1.5 border-t border-dashed border-white/10 pt-3">
                        <template x-for="item in (receipt?.items || [])" :key="item.id">
                            <div class="flex justify-between">
                                <span class="text-slate-400"><span x-text="item.qty"></span>× <span x-text="item.product_name"></span></span>
                                <span class="tabular text-slate-300" x-text="item.subtotal_label"></span>
                            </div>
                        </template>
                    </div>

                    <div class="space-y-1.5 border-t border-dashed border-white/10 pt-3">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Sewa</span>
                            <span class="tabular text-slate-300" x-text="receipt?.rental_fee_label"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">F&B</span>
                            <span class="tabular text-slate-300" x-text="receipt?.items_total_label"></span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/10 pt-3">
                        <span class="font-bold text-white">TOTAL</span>
                        <span class="tabular text-lg font-extrabold text-white" x-text="receipt?.grand_total_label"></span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg bg-ink-900 px-3 py-2">
                        <span class="text-slate-500">Bayar via</span>
                        <span class="font-bold text-brand-300" x-text="receipt?.payment_method_label"></span>
                    </div>

                    <p class="pt-1 text-center text-[10px] text-slate-600">
                        Terima kasih sudah bermain di Rebite Playstation!
                    </p>
                </div>

                <div class="flex gap-2 border-t border-white/5 px-5 py-4">
                    <button type="button" x-on:click="closeModal(); receipt = null" class="btn-ghost flex-1">Tutup</button>
                    <button type="button" x-on:click="printReceipt()" class="btn-primary flex-1">
                        <x-icon name="printer" class="h-4 w-4" />
                        Cetak
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
