@extends('layouts.app')

@section('title', $package->exists ? 'Ubah Paket' : 'Tambah Paket')
@section('page-title', $package->exists ? 'Ubah Paket ' . $package->name : 'Tambah Paket Sewa')
@section('page-subtitle', 'Harga paket sudah final — tidak dihitung per jam, berbeda dari Open Play')

@section('content')
    <div class="mx-auto max-w-2xl">
        <form method="POST"
              action="{{ $package->exists ? route('owner.rate-packages.update', $package) : route('owner.rate-packages.store') }}"
              class="card space-y-5 p-5">
            @csrf
            @if ($package->exists) @method('PUT') @endif

            <x-input
                label="Nama Paket"
                name="name"
                :value="old('name', $package->name)"
                placeholder="Paket Hemat 3 Jam"
                required
                maxlength="100"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-select
                    label="Berlaku Untuk Tipe Unit"
                    name="unit_type"
                    :value="old('unit_type', $package->unit_type)"
                    :options="array_combine($unitTypes, $unitTypes)"
                    placeholder="Semua tipe (global)"
                />

                <x-input
                    label="Durasi (menit)"
                    name="duration_minutes"
                    type="number"
                    min="5"
                    max="1440"
                    step="5"
                    :value="old('duration_minutes', $package->duration_minutes ?? 60)"
                    required
                />

                <x-input
                    label="Harga Paket (Rp)"
                    name="price"
                    type="number"
                    min="0"
                    step="500"
                    :value="old('price', $package->price ?? 0)"
                    required
                    inputmode="numeric"
                />

                <x-input
                    label="Urutan Tampil"
                    name="sort_order"
                    type="number"
                    min="0"
                    max="999"
                    :value="old('sort_order', $package->sort_order ?? 0)"
                    required
                />
            </div>

            <x-input
                label="Keterangan Singkat"
                name="description"
                :value="old('description', $package->description)"
                placeholder="Hemat 20% dibanding sewa per jam"
                maxlength="255"
            />

            <x-checkbox
                name="is_active"
                :checked="old('is_active', $package->is_active ?? true)"
            >
                Paket Aktif
            </x-checkbox>

            <div class="flex flex-wrap gap-3 border-t border-white/5 pt-4">
                <button type="submit" class="btn-primary flex-1">
                    <x-icon name="save" class="h-4 w-4" />
                    {{ $package->exists ? 'Simpan Perubahan' : 'Tambah Paket' }}
                </button>

                <a href="{{ route('owner.rate-packages.index') }}" class="btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
