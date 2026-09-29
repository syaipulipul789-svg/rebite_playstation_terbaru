@extends('layouts.app')

@section('title', $product->exists ? 'Ubah Produk' : 'Tambah Produk')
@section('page-title', $product->exists ? 'Ubah ' . $product->name : 'Tambah Produk Baru')
@section('page-subtitle', 'Stok berkurang otomatis ketika kasir menambahkan item ke sesi yang sedang berjalan')

@section('content')
    <div class="mx-auto max-w-2xl">
        <form method="POST"
              action="{{ $product->exists ? route('owner.products.update', $product) : route('owner.products.store') }}"
              class="card space-y-5 p-5">
            @csrf
            @if ($product->exists) @method('PUT') @endif

            <x-input
                label="Nama Produk"
                name="name"
                :value="old('name', $product->name)"
                placeholder="Indomie Goreng"
                required
                maxlength="100"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-select
                    label="Kategori"
                    name="category"
                    :value="old('category', $product->category?->value ?? 'SNACK')"
                    :options="$productCategoryOptions"
                    required
                />

                <x-input
                    label="Harga (Rp)"
                    name="price"
                    type="number"
                    step="500"
                    min="0"
                    :value="old('price', $product->price ?? 0)"
                    required
                    inputmode="numeric"
                />

                <x-input
                    label="Stok Tersedia"
                    name="stock"
                    type="number"
                    min="0"
                    :value="old('stock', $product->stock ?? 0)"
                    required
                />

                <x-input
                    label="Batas Stok Menipis"
                    name="low_stock_threshold"
                    type="number"
                    min="0"
                    :value="old('low_stock_threshold', $product->low_stock_threshold ?? 5)"
                    required
                />
            </div>

            {{-- Checkbox tanpa hidden 0: ProductRequest menormalisasi is_active
                 lewat boolean(), jadi field yang tidak terkirim dianggap false. --}}
            <x-checkbox name="is_active" value="1" :checked="old('is_active', $product->is_active ?? true)">
                Produk aktif — bisa dipilih kasir saat menambah pesanan
            </x-checkbox>

            <div class="flex flex-wrap gap-3 border-t border-white/5 pt-4">
                <button type="submit" class="btn-primary flex-1">
                    <x-icon name="save" class="h-4 w-4" />
                    {{ $product->exists ? 'Simpan Perubahan' : 'Tambah Produk' }}
                </button>

                <a href="{{ route('owner.products.index') }}" class="btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
