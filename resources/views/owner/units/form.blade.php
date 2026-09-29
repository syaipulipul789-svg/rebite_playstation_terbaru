@extends('layouts.app')

@section('title', $unit->exists ? 'Ubah Unit' : 'Tambah Unit')
@section('page-title', $unit->exists ? 'Ubah Unit ' . $unit->code : 'Tambah Unit Baru')
@section('page-subtitle', 'Tarif per jam dipakai untuk menghitung biaya sewa dan open play')

@section('content')
    <div class="mx-auto max-w-2xl space-y-5">
        <form method="POST"
              action="{{ $unit->exists ? route('owner.units.update', $unit) : route('owner.units.store') }}"
              class="card space-y-5 p-5">
            @csrf
            @if ($unit->exists) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input
                        label="Kode Unit"
                        name="code"
                        :value="old('code', $unit->code)"
                        placeholder="PS5-01"
                        required
                        maxlength="30"
                    />
                </div>

                <div>
                    <x-input
                        label="Nama Unit"
                        name="name"
                        :value="old('name', $unit->name)"
                        placeholder="PS5 Reguler 1"
                        required
                        maxlength="100"
                    />
                </div>

                <div>
                    <x-input
                        label="Tipe Konsol"
                        name="type"
                        :value="old('type', $unit->type)"
                        placeholder="PS5"
                        list="unit-type-list"
                        required
                        maxlength="50"
                    />

                    <datalist id="unit-type-list">
                        @foreach ($types ?? [] as $type)
                            <option value="{{ $type }}"></option>
                        @endforeach
                        @foreach (['PS3', 'PS4', 'PS5', 'Nintendo Switch', 'PC Gaming', 'Open Play'] as $type)
                            <option value="{{ $type }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div>
                    <x-input
                        label="Tarif per Jam (Rp)"
                        name="hourly_rate"
                        type="number"
                        step="500"
                        min="0"
                        :value="old('hourly_rate', $unit->hourly_rate ?? 0)"
                        required
                        inputmode="numeric"
                    />
                </div>

                <div>
                    <x-select
                        label="Status Unit"
                        name="status"
                        :value="old('status', $unit->status?->value ?? 'READY')"
                        :options="$unitStatusOptions"
                        required
                    />
                </div>

                <div>
                    <x-input
                        label="Lokasi"
                        name="location"
                        :value="old('location', $unit->location)"
                        placeholder="Lantai 1, sisi kanan"
                        maxlength="100"
                    />
                </div>
            </div>

            <x-textarea
                label="Catatan Internal"
                name="notes"
                :value="old('notes', $unit->notes)"
                rows="3"
                maxlength="500"
                placeholder="Keterangan servis, kelengkapan remote, dan sejenisnya."
            />

            <div class="flex flex-wrap gap-3 border-t border-white/5 pt-4">
                <button type="submit" class="btn-primary flex-1">
                    <x-icon name="save" class="h-4 w-4" />
                    {{ $unit->exists ? 'Simpan Perubahan' : 'Tambah Unit' }}
                </button>

                <a href="{{ route('owner.units.index') }}" class="btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
