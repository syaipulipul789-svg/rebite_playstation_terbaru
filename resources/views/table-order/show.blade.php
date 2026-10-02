@use('App\Support\Duration', 'Duration')

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="description" content="Pemesanan mandiri {{ $unit->code }} — {{ config('app.name') }}">

    <title>Menu {{ $unit->code }} — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased"
      x-data="tableMenu({ storeUrl: @js($storeUrl), menu: @js($menu), remainingSeconds: {{ $session->remainingSeconds() } })"
      x-cloak>

    {{-- Error dari server: sesi sudah selesai / tidak ada stok lagi. --}}
    @if ($errors->any())
        <div class="mx-auto max-w-lg px-4 pt-4">
            @foreach ($errors->all() as $error)
                <p class="flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-xs text-rose-200">
                    <x-icon name="triangle-alert" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span>{{ $error }}</span>
                </p>
            @endforeach
        </div>
    @endif

    {{-- ================= HEADER: unit + sisa waktu ================= --}}
    <header class="sticky top-0 z-30 border-b border-white/5 bg-ink-950/95 backdrop-blur">
        <div class="mx-auto max-w-3xl px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-extrabold text-white">
                        {{ $unit->code }} <span class="font-semibold text-slate-500">&middot; {{ $unit->name }}</span>
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">{{ $session->package_name }}</p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Sisa main</p>
                    <p class="tabular text-lg font-extrabold"
                       :class="remainingSeconds <= 0 ? 'text-rose-400' : 'text-emerald-300'"
                       x-text="remainingLabel">{{ Duration::toClock($session->remainingSeconds()) }}</p>
                </div>
            </div>

            <p class="mt-2 flex items-center gap-1.5 text-[11px] text-slate-500">
                <x-icon name="receipt" class="h-3.5 w-3.5 shrink-0" />
                Pesanan dikirim langsung ke kasir. Pembayaran digabung dengan biaya rental.
            </p>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 pb-40 pt-4">

        {{-- ================= MENU ================= --}}
        @forelse ($menu as $group)
            <section class="mb-6">
                <h2 class="mb-2.5 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-500">
                    <span class="h-px flex-1 bg-white/5"></span>
                    {{ $group['label'] }}
                </h2>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($group['products'] as $product)
                        <button type="button"
                                class="card flex items-center justify-between gap-3 p-3 text-left transition"
                                :class="inCart({{ $product['id'] }}) ? 'ring-2 ring-brand-500/60' : 'card-hover'"
                                x-on:click="add({{ $product['id'] }})"
                                :disabled="busy">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold text-white">{{ $product['name'] }}</span>
                                <span class="tabular mt-0.5 block text-xs text-slate-400">{{ $product['price_label'] }}</span>
                                @if ($product['low_stock'])
                                    <span class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-amber-400">
                                        <x-icon name="triangle-alert" class="h-3 w-3" />
                                        sisa {{ $product['stock'] }}
                                    </span>
                                @endif
                            </span>

                            <span class="shrink-0 rounded-lg bg-brand-500/15 px-2.5 py-1.5 text-brand-300 ring-1 ring-inset ring-brand-500/30"
                                  x-show="qtyOf({{ $product['id'] }}) === 0">
                                <x-icon name="plus" class="h-4 w-4" />
                            </span>

                            <span class="tabular shrink-0 rounded-lg bg-emerald-500/15 px-2.5 py-1 text-sm font-extrabold text-emerald-300 ring-1 ring-inset ring-emerald-500/30"
                                  x-show="qtyOf({{ $product['id'] }}) > 0"
                                  x-text="qtyOf({{ $product['id'] }})"></span>
                        </button>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="card px-6 py-14 text-center">
                <x-icon name="utensils-crossed" class="mx-auto h-9 w-9 text-slate-700" />
                <p class="mt-3 text-sm font-semibold text-slate-400">Menu sedang kosong.</p>
                <p class="mt-1 text-xs text-slate-600">Semua produk sedang habis. Hubungi kasir bila ini tidak sesuai.</p>
            </div>
        @endforelse
    </main>

    {{-- ================= KERANJANG (bottom sheet) ================= --}}
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/5 bg-ink-900/95 backdrop-blur"
         x-show="cartOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0">

        <div class="mx-auto max-w-3xl">
            <template x-if="cart.length > 0">
                <div class="max-h-[55vh] overflow-y-auto border-b border-white/5 px-4 py-3">
                    <template x-for="(line, index) in cart" :key="line.uid">
                        <div class="flex items-start gap-3 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-white" x-text="line.name"></p>
                                <p class="tabular mt-0.5 text-[11px] text-slate-500" x-text="line.priceLabel"></p>

                                <input type="text"
                                       class="input mt-1.5 py-1.5 text-xs"
                                       :placeholder="'Catatan, mis. pedas / telor ceplok'"
                                       maxlength="120"
                                       x-model="line.notes"
                                       x-on:input="syncNotes()">
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-slate-300"
                                        x-on:click="decrement(index)" :disabled="busy">
                                    <x-icon name="minus" class="h-3.5 w-3.5" />
                                </button>

                                <span class="tabular w-8 text-center text-sm font-extrabold text-white" x-text="line.qty"></span>

                                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-slate-300"
                                        x-on:click="increment(index)" :disabled="busy">
                                    <x-icon name="plus" class="h-3.5 w-3.5" />
                                </button>

                                <button type="button" class="ml-1 grid h-8 w-8 place-items-center rounded-lg bg-rose-500/10 text-rose-300"
                                        x-on:click="remove(index)" :disabled="busy">
                                    <x-icon name="trash-2" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div class="px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <button type="button" class="btn-ghost shrink-0" x-on:click="cartOpen = false">
                        <x-icon name="chevron-down" class="h-4 w-4" />
                        Menu
                    </button>

                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Total</p>
                        <p class="tabular text-lg font-extrabold text-white" x-text="totalLabel">Rp 0</p>
                    </div>

                    <button type="button" class="btn-primary shrink-0"
                            x-on:click="openForm()"
                            :disabled="cart.length === 0">
                        <i data-lucide="send" class="h-4 w-4"></i>
                        Kirim
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bar pemicu keranjang: selalu kelihatan supaya pelanggan tahu ada isi. --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-white/5 bg-ink-900/95 px-4 py-3 backdrop-blur"
         x-show="! cartOpen && cart.length > 0">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
            <p class="text-sm text-slate-300">
                <span class="tabular font-extrabold text-white" x-text="cartCount">0</span> item
                &middot; <span class="tabular font-bold text-brand-300" x-text="totalLabel">Rp 0</span>
            </p>

            <button type="button" class="btn-primary" x-on:click="cartOpen = true">
                <x-icon name="shopping-bag" class="h-4 w-4" />
                Lihat Keranjang
            </button>
        </div>
    </div>

    {{-- ================= MODAL IDENTITAS ================= --}}
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/80 p-0 backdrop-blur-sm sm:items-center sm:p-4"
         x-show="formOpen"
         x-on:keydown.escape.window="closeForm()"
         x-cloak>
        <div class="w-full max-w-md rounded-t-2xl border border-white/5 bg-ink-950 p-5 sm:rounded-2xl"
             x-show="formOpen"
             x-transition
             x-on:click.outside="closeForm()">
            <h2 class="text-base font-extrabold text-white">Data untuk kasir</h2>
            <p class="mt-1 text-xs text-slate-500">
                Dipakai kasir saat mengatur pembayaran. <span class="tabular font-bold text-brand-300" x-text="totalLabel"></span>
            </p>

            <form class="mt-4 space-y-3"
                  x-on:submit.prevent="submit()">
                <div>
                    <label for="to-name" class="label">Nama</label>
                    <input id="to-name" name="customer_name" type="text" class="input" maxlength="255"
                           autocomplete="name" required x-model="customerName">
                </div>

                <div>
                    <label for="to-phone" class="label">Nomor WhatsApp</label>
                    <input id="to-phone" name="customer_phone" type="tel" class="input" maxlength="30"
                           autocomplete="tel" inputmode="tel" required x-model="customerPhone">
                </div>

                <div>
                    <label for="to-notes" class="label">Catatan untuk kasir (opsional)</label>
                    <input id="to-notes" name="notes" type="text" class="input" maxlength="500"
                           x-model="notes" placeholder="mis. bayar di kasir setelah main selesai">
                </div>

                <p x-show="error" x-text="error" class="text-xs text-rose-400"></p>

                <div class="flex items-center gap-2 pt-1">
                    <button type="button" class="btn-ghost flex-1" x-on:click="closeForm()">Batal</button>
                    <button type="submit" class="btn-primary flex-1" :disabled="busy">
                        <i x-show="busy" data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                        <i x-show="! busy" data-lucide="check" class="h-4 w-4"></i>
                        Kirim Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
