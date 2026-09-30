@use('App\Enums\ProductCategory', 'ProductCategory')
@use('App\Support\Money', 'Money')

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Label Barcode — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Halaman ini sengaja berdiri sendiri (tidak extends layout internal)
           supaya hasil cetak hanya berisi label — tanpa sidebar, topbar,
           maupun footer aplikasi. */
        @media print {
            @page { margin: 8mm; }
            body { background: #ffffff !important; }
            #sheet-controls { display: none !important; }
            #label-sheet { gap: 4mm !important; }
            .label-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">

        {{-- ================= KONTROL (tidak ikut tercetak) ================= --}}
        <div id="sheet-controls" class="mb-6 space-y-4">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-extrabold text-white">Label Barcode Produk</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Cetak lalu tempel di kemasan. Pelanggan memindai label ini dari halaman pesanan.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('owner.products.index') }}" class="btn-ghost">
                        <x-icon name="arrow-left-right" class="h-4 w-4" />
                        Kembali
                    </a>

                    <button type="button" class="btn-primary" x-on:click="window.print()">
                        <x-icon name="printer" class="h-4 w-4" />
                        Cetak
                    </button>
                </div>
            </header>

            <form method="GET" action="{{ route('owner.products.labels') }}"
                  class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="label-category" class="label">Kategori</label>
                    <select id="label-category" name="category" class="input">
                        <option value="">Semua</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->value }}" @selected($category === $option->value)>
                                {{ $option->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn-ghost">Terapkan</button>

                <span class="badge bg-ink-800 text-slate-300 ring-1 ring-inset ring-white/10">
                    {{ $products->count() }} label
                </span>
            </form>

            @if ($missingCount > 0)
                <p class="flex items-start gap-2 rounded-xl bg-amber-500/10 px-3 py-2.5 text-xs text-amber-200">
                    <x-icon name="triangle-alert" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span>
                        {{ $missingCount }} produk aktif belum punya barcode sehingga tidak ikut dicetak.
                        Isi dulu di <a href="{{ route('owner.products.index') }}" class="font-semibold underline">halaman produk</a>.
                    </span>
                </p>
            @endif
        </div>

        {{-- ================= SHEET LABEL ================= --}}
        @if ($products->isEmpty())
            <div class="card px-6 py-16 text-center">
                <x-icon name="barcode" class="mx-auto h-10 w-10 text-slate-700" />
                <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada produk aktif dengan barcode.</p>
            </div>
        @else
            <div id="label-sheet" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($products as $product)
                    <div class="label-card flex flex-col items-center justify-center rounded-xl border border-slate-300 bg-white p-3 text-center">
                        <p class="line-clamp-2 text-[11px] font-bold leading-tight text-slate-900">{{ $product->name }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase text-slate-500">
                            {{ Money::format($product->price) }} · {{ $product->category->label() }}
                        </p>

                        {{-- Diisi SVG Code128 oleh resources/js/barcode-labels.js --}}
                        <div class="my-1.5 w-full" data-barcode="{{ $product->barcode }}"></div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
