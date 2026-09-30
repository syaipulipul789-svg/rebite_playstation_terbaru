@extends('layouts.app')

@section('title', 'Kelola Produk')
@section('page-title', 'Kelola Produk F&B')
@section('page-subtitle', 'Stok dan harga produk yang bisa ditambahkan kasir saat sesi berjalan')

@section('content')
    <div class="space-y-5">

        @if ($lowStock->isNotEmpty())
            <div class="card border-amber-500/25 bg-amber-500/[0.05] p-4">
                <div class="flex items-start gap-3">
                    <x-icon name="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-amber-200">Stok perlu diisi ulang</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($lowStock as $product)
                                <span class="badge bg-amber-500/15 text-amber-200">
                                    {{ $product->name }} — sisa {{ $product->stock }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <a href="{{ route('owner.products.index', ['low_stock' => 1]) }}" class="btn-subtle shrink-0 text-xs text-amber-200">
                        Tampilkan semua
                    </a>
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('owner.products.index') }}"
                   @class([
                       'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                       'bg-brand-500 text-white' => empty($filters['category']),
                       'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => ! empty($filters['category']),
                   ])>Semua</a>

                @foreach ($productCategoryOptions as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['category' => $value, 'page' => null]) }}"
                       @class([
                           'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                           'bg-brand-500 text-white' => ($filters['category'] ?? '') === $value,
                           'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => ($filters['category'] ?? '') !== $value,
                       ])>{{ $label }}</a>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('owner.products.labels') }}" class="btn-ghost">
                    <x-icon name="barcode" class="h-4 w-4" />
                    Cetak Label
                </a>

                <a href="{{ route('owner.products.create') }}" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Tambah Produk
                </a>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Barcode</th>
                            <th>Kategori</th>
                            <th class="text-right">Harga</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($products as $product)
                            <tr @class(['opacity-50' => ! $product->is_active])>
                                <td>
                                    <p class="text-xs font-semibold text-slate-200">{{ $product->name }}</p>
                                    <p class="tabular text-[10px] text-slate-500">batas menipis: {{ $product->low_stock_threshold }}</p>
                                </td>

                                <td>
                                    @if ($product->hasBarcode())
                                        <span class="tabular text-xs text-slate-400">{{ $product->barcode }}</span>
                                    @else
                                        <span class="text-[10px] font-semibold uppercase text-amber-300/80">Belum ada</span>
                                    @endif
                                </td>

                                <td>
                                    <x-badge variant="slate">{{ $product->category->label() }}</x-badge>
                                </td>

                                <td class="tabular text-right text-xs font-semibold text-white">
                                    {{ \App\Support\Money::format($product->price, false) }}
                                </td>

                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <form method="POST" action="{{ route('owner.products.stock', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="delta" value="-1">
                                            <button type="submit" class="grid h-6 w-6 place-items-center rounded-md bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white">
                                                <x-icon name="chevron-left" class="h-3 w-3" />
                                            </button>
                                        </form>

                                        <span @class([
                                            'tabular w-8 text-center text-xs font-bold',
                                            'text-rose-400' => $product->stock <= 0,
                                            'text-amber-300' => $product->isLowStock() && $product->stock > 0,
                                            'text-slate-200' => ! $product->isLowStock(),
                                        ])>{{ $product->stock }}</span>

                                        <form method="POST" action="{{ route('owner.products.stock', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="delta" value="1">
                                            <button type="submit" class="grid h-6 w-6 place-items-center rounded-md bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white">
                                                <x-icon name="chevron-right" class="h-3 w-3" />
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <x-badge :variant="$product->is_active ? 'emerald' : 'slate'" dot>
                                        {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('owner.products.edit', $product) }}" class="btn-subtle text-[11px]">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            Ubah
                                        </a>

                                        <form method="POST" action="{{ route('owner.products.destroy', $product) }}"
                                              onsubmit="return confirm('Nonaktifkan / hapus produk {{ $product->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-subtle text-[11px] text-rose-400/70 hover:text-rose-300">
                                                <x-icon name="trash-2" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-xs text-slate-600">
                                    Belum ada produk. Tambahkan snack atau minuman untuk kasir.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="border-t border-white/5 px-5 py-3">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
@endsection
