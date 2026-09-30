@use('App\Support\Money', 'Money')

<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Pesan snack & minuman Rebite Playstation dengan memindai barcode produk.">

    <title>Pesanan Barcode — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

@if ($order === null)
    {{-- ==================================================================
         STEP 1 — IDENTITAS PESANAN (belum ada pesanan aktif)
    =================================================================== --}}
    <div class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
        <div class="mb-6 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30">
                <x-icon name="scan-line" class="h-6 w-6" />
            </span>
            <h1 class="mt-4 text-2xl font-extrabold text-white sm:text-3xl">Pesan via Barcode</h1>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                Arahkan kamera ke barcode produk untuk masukkan ke pesanan. Pembayaran tetap di kasir.
            </p>
        </div>

        <form method="POST" action="{{ route('customer.order.store') }}"
              class="card space-y-4 p-5 sm:p-6">
            @csrf

            <div>
                <label for="co-name" class="label">Nama</label>
                <input id="co-name" name="customer_name" type="text" class="input" required
                       maxlength="255" placeholder="Nama lengkap" value="{{ old('customer_name') }}">
                @error('customer_name')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="co-phone" class="label">No. WhatsApp</label>
                <input id="co-phone" name="customer_phone" type="tel" class="input" required
                       maxlength="30" placeholder="08xxxxxxxxxx" value="{{ old('customer_phone') }}">
                @error('customer_phone')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="co-unit" class="label">Bermain di Unit <span class="normal-case text-slate-600">(opsional)</span></label>
                <select id="co-unit" name="unit_id" class="input">
                    <option value="">Tidak menyewa konsol</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected((int) old('unit_id') === $unit->id)>
                            {{ $unit->code }} — {{ $unit->name }} ({{ $unit->status->label() }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-600">Membantu kasir mengantar pesanan ke tempat dudukmu.</p>
                @error('unit_id')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="co-booking" class="label">Kode Booking <span class="normal-case text-slate-600">(opsional)</span></label>
                <input id="co-booking" name="booking_code" type="text" class="input"
                       maxlength="20" placeholder="BK-0001" value="{{ old('booking_code') }}">
                <p class="mt-1 text-xs text-slate-600">Masukkan kode bookingmu agar pesanan menempel ke sesi sewa.</p>
                @error('booking_code')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary w-full">
                <x-icon name="scan-line" class="h-4 w-4" />
                Mulai Scan
            </button>

            <a href="{{ route('customer.home') }}" class="btn-subtle w-full">Kembali</a>
        </form>
    </div>
@else
    {{-- ==================================================================
         STEP 2 — SCAN & KERANJANG
    =================================================================== --}}
    <div
        x-data="barcodeOrder({
            orderToken: @js($order->token),
            order: @js($orderPayload),
            scanUrl: @js(route('customer.order.scan', $order->token)),
            showUrl: @js(route('customer.order.show', $order->token)),
        })"
        class="mx-auto flex min-h-screen max-w-lg flex-col px-4 pb-8 pt-6 sm:px-6"
    >

        {{-- HEADER --}}
        <header class="mb-5 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Pesanan</p>
                <p class="text-xl font-extrabold tracking-wider text-brand-300">{{ $order->code }}</p>
                <p class="truncate text-xs text-slate-500">
                    {{ $order->customer_name }}
                    @if ($order->unit)
                        · {{ $order->unit->code }}
                    @endif
                </p>
            </div>

            <span class="badge shrink-0" x-bind:class="statusClass()" x-text="order?.status_label"></span>
        </header>

        {{-- PESANAN SUDAH DIBAYAR / DIBATALKAN --}}
        <template x-if="order && !isEditable">
            <div class="card space-y-4 p-5 text-center sm:p-6">
                <x-icon name="check-circle-2" class="mx-auto h-10 w-10 text-emerald-300" />
                <p class="text-sm font-bold text-white" x-text="order?.status_label"></p>
                <p class="text-sm text-slate-400">
                    Total <span class="font-extrabold text-white" x-text="order?.total_label"></span>
                </p>
                <p class="text-xs text-slate-500">Halaman ini otomatis berubah begitu kasir menyelesaikan pesananmu.</p>
                <a href="{{ route('customer.home') }}" class="btn-primary w-full">Selesai</a>
            </div>
        </template>

        {{-- SCAN AKTIF --}}
        <template x-if="isEditable">
            <div class="space-y-5">

                {{-- PEMINDAI KAMERA --}}
                <div class="card overflow-hidden p-4">
                    <div id="barcode-reader" class="w-full overflow-hidden rounded-xl bg-ink-950"></div>

                    <div x-cloak x-show="!scanning && !cameraStarting"
                         class="mt-3 rounded-xl bg-ink-950 px-4 py-10 text-center">
                        <x-icon name="camera-off" class="mx-auto h-8 w-8 text-slate-700" />
                        <p class="mt-3 text-sm font-semibold text-slate-400">Kamera belum dinyalakan</p>
                        <p class="mt-1 text-xs text-slate-600">Nyalakan kamera, atau ketik kode barcode di bawah.</p>
                    </div>

                    <p x-cloak x-show="cameraError" class="mt-3 rounded-xl bg-rose-500/10 px-3 py-2.5 text-xs text-rose-200"
                       x-text="cameraError"></p>

                    <div class="mt-3 flex gap-2">
                        <button type="button" class="btn-primary flex-1" x-on:click="toggleScanner()"
                                x-bind:disabled="cameraStarting">
                            <span x-text="scanning ? 'Matikan Kamera' : (cameraStarting ? 'Menyala...' : 'Nyalakan Kamera')"></span>
                        </button>
                    </div>

                    {{-- Input manual / scanner USB --}}
                    <form class="mt-3 flex gap-2" x-on:submit.prevent="submitManual()">
                        <input type="text" class="input flex-1" placeholder="Atau ketik / pindai kode barcode"
                               x-model="manualCode" autocomplete="off" autocapitalize="characters">
                        <button type="submit" class="btn-ghost shrink-0" x-bind:disabled="busy || manualCode.trim() === ''">
                            <x-icon name="plus" class="h-4 w-4" />
                        </button>
                    </form>
                </div>

                {{-- FLASH --}}
                <p x-cloak x-show="message" class="rounded-xl bg-emerald-500/10 px-3 py-2.5 text-sm text-emerald-200"
                   x-text="message"></p>
                <p x-cloak x-show="error" class="rounded-xl bg-rose-500/10 px-3 py-2.5 text-sm text-rose-200"
                   x-text="error"></p>

                {{-- KERANJANG --}}
                <div class="card overflow-hidden">
                    <header class="flex items-center justify-between gap-3 border-b border-white/5 px-4 py-3">
                        <h2 class="text-sm font-bold text-white">Pesananmu</h2>
                        <span class="badge bg-ink-800 text-slate-300 ring-1 ring-inset ring-white/10"
                              x-text="order?.item_count + ' item'"></span>
                    </header>

                    <div x-cloak x-show="isEmpty" class="px-4 py-10 text-center">
                        <x-icon name="shopping-bag" class="mx-auto h-8 w-8 text-slate-700" />
                        <p class="mt-3 text-sm text-slate-500">Pesanan masih kosong.</p>
                        <p class="mt-1 text-xs text-slate-600">Pindai barcode produk untuk menambahkan.</p>
                    </div>

                    <ul x-show="!isEmpty" class="divide-y divide-white/5">
                        <template x-for="item in items" :key="item.id">
                            <li class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-200" x-text="item.product_name"></p>
                                    <p class="tabular text-xs text-slate-500">
                                        <span x-text="item.price_label"></span> × <span x-text="item.qty"></span>
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <div class="flex items-center gap-1 rounded-lg bg-ink-900 p-0.5">
                                        <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-white/5 hover:text-white"
                                                x-on:click="changeQty(item, item.qty - 1)" x-bind:disabled="busy">
                                            <x-icon name="minus" class="h-3.5 w-3.5" />
                                        </button>
                                        <span class="tabular w-6 text-center text-xs font-bold text-white" x-text="item.qty"></span>
                                        <button type="button" class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-white/5 hover:text-white"
                                                x-on:click="changeQty(item, item.qty + 1)" x-bind:disabled="busy">
                                            <x-icon name="plus" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>

                                    <span class="tabular w-24 text-right text-sm font-bold text-white" x-text="item.subtotal_label"></span>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <footer class="flex items-center justify-between gap-3 border-t border-white/5 bg-ink-900/50 px-4 py-3">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total</span>
                        <span class="tabular text-lg font-extrabold text-brand-300" x-text="totalLabel"></span>
                    </footer>
                </div>

                {{-- KIRIM KE KASIR --}}
                <div class="space-y-2">
                    <button type="button" class="btn-primary w-full" x-on:click="place()"
                            x-bind:disabled="placing || isEmpty">
                        <x-icon name="send" class="h-4 w-4" />
                        <span x-text="placing ? 'Mengirim...' : 'Kirim Pesanan ke Kasir'"></span>
                    </button>

                    <p class="text-center text-[11px] text-slate-600">
                        Tunjukkan kode <span class="font-bold text-brand-300">{{ $order->code }}</span> ke kasir untuk pembayaran.
                    </p>
                </div>
            </div>
        </template>
    </div>
@endif

</body>
</html>
