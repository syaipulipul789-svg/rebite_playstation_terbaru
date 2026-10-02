@use('App\Support\Duration', 'Duration')
@use('App\Support\Money', 'Money')

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="description" content="Status pesanan {{ $order->code }} — {{ config('app.name') }}">

    <title>Pesanan {{ $order->code }} — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased"
      x-data="tableOrderStatus({ statusUrl: @js($statusUrl), initial: @js($initialStatus), pollMs: 6000 })"
      x-cloak>

    <main class="mx-auto flex max-w-md flex-col px-5 py-8">

        {{-- ================= STATUS UTAMA ================= --}}
        <div class="text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30"
                  x-show="status === 'COMPLETED' || status === 'SERVED'">
                <x-icon name="check-circle-2" class="h-8 w-8" />
            </span>

            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-orange-500/15 text-orange-300 ring-1 ring-inset ring-orange-500/30"
                  x-show="status === 'PREPARING'">
                <x-icon name="utensils-crossed" class="h-8 w-8" />
            </span>

            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30"
                  x-show="status === 'CANCELLED'">
                <x-icon name="x-circle" class="h-8 w-8" />
            </span>

            <h1 class="mt-5 text-2xl font-extrabold text-white">Pesanan diterima</h1>
            <p class="mt-1 text-sm text-slate-500">
                Nomor <span class="font-mono font-bold text-slate-300">{{ $order->code }}</span>
                &middot; {{ $unit->code }}
            </p>

            <div class="mt-4 flex justify-center">
                <span class="badge text-sm" :class="badgeClass" x-text="statusLabel">{{ $order->status->label() }}</span>
            </div>
        </div>

        {{-- ================= LANGKAH ================= --}}
        <ol class="mt-8 space-y-2">
            @foreach ([
                ['PREPARING', 'Diterima kasir', 'Pesanan sudah masuk ke dapur.'],
                ['SERVED', 'Sedang diantar', 'Barang diantar ke meja Anda.'],
                ['COMPLETED', 'Selesai', 'Silakan bayar di kasir.'],
            ] as $index => [$key, $title, $hint])
                <li class="flex items-start gap-3 rounded-xl border border-white/5 bg-ink-900 px-3.5 py-3"
                    :class="stepReached({{ $index }}, '{{ $key }}') ? 'opacity-100' : 'opacity-45'">
                    <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-[11px] font-extrabold"
                          :class="stepReached({{ $index }}, '{{ $key }}') ? 'bg-brand-500/20 text-brand-300' : 'bg-white/5 text-slate-500'"
                          >{{ $index + 1 }}</span>
                    <span>
                        <span class="block text-sm font-bold text-white">{{ $title }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        {{-- ================= RINCIAN PESANAN ================= --}}
        <section class="card mt-6 p-4">
            <header class="flex items-center justify-between gap-3">
                <h2 class="text-xs font-bold uppercase tracking-widest text-slate-500">Rincian</h2>
                <span class="text-[11px] text-slate-600" x-text="itemCount + ' item'">{{ $order->itemCount() }} item</span>
            </header>

            <ul class="mt-3 space-y-2.5">
                <template x-for="item in items" :key="item.name + (item.notes ?? '')">
                    <li class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-200">
                                <span class="tabular" x-text="item.qty + 'x'"></span>
                                <span x-text="item.name"></span>
                            </span>
                            <span class="mt-0.5 block text-[11px] italic text-amber-300/80"
                                  x-show="item.notes"
                                  x-text="item.notes"></span>
                        </span>
                        <span class="tabular shrink-0 text-sm text-slate-400" x-text="item.subtotal_label"></span>
                    </li>
                </template>
            </ul>

            <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-3">
                <span class="text-sm font-bold text-slate-300">Total</span>
                <span class="tabular text-lg font-extrabold text-white" x-text="total">{{ Money::format($order->totalPrice()) }}</span>
            </div>
        </section>

        {{-- ================= CATATAN KE KASIR ================= --}}
        @if ($order->notes)
            <p class="mt-4 rounded-xl border border-white/5 bg-ink-900 px-3.5 py-3 text-xs text-slate-400">
                <span class="font-bold text-slate-300">Catatan Anda:</span> {{ $order->notes }}
            </p>
        @endif

        <p class="mt-6 text-center text-xs text-slate-600">
            <span x-show="! isFinal">Halaman ini memperbarui status otomatis.</span>
            <span x-show="isFinal">Status pesanan sudah final. Hubungi kasir bila ada kendala.</span>
        </p>

        <div class="mt-5">
            <a href="{{ route('table.order.show', ['unit_code' => $unit->code]) }}" class="btn-ghost w-full">
                <x-icon name="plus" class="h-4 w-4" />
                Pesan Lagi
            </a>
        </div>
    </main>

</body>
</html>
