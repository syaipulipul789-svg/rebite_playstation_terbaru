@extends('layouts.app')

@section('title', 'Kelola Paket Sewa')
@section('page-title', 'Kelola Paket Sewa')
@section('page-subtitle', 'Paket durasi yang dipilih kasir saat memulai sewa unit')

@section('content')
    <div class="space-y-5">

        <form method="GET" action="{{ route('owner.rate-packages.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[200px] flex-1">
                <label for="unit_type" class="label">Terapkan Untuk Tipe</label>
                <select id="unit_type" name="unit_type" class="input">
                    <option value="">Semua tipe (global)</option>
                    @foreach ($unitTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['unit_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>

                <p class="mt-1.5 text-xs text-slate-500">
                    Paket tanpa tipe berlaku untuk semua konsol. Paket bertipe hanya muncul untuk unit dengan tipe tersebut.
                </p>
            </div>

            <button type="submit" class="btn-primary">
                <x-icon name="search" class="h-4 w-4" />
                Terapkan
            </button>

            <a href="{{ route('owner.rate-packages.create') }}" class="btn-success">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Paket
            </a>
        </form>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Berlaku Untuk</th>
                            <th class="text-center">Durasi</th>
                            <th class="text-right">Harga</th>
                            <th class="text-right">Per Jam</th>
                            <th class="text-center">Urutan</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($packages as $package)
                            <tr @class(['opacity-50' => ! $package->is_active])>
                                <td>
                                    <p class="text-xs font-semibold text-slate-200">{{ $package->name }}</p>
                                    @if ($package->description)
                                        <p class="line-clamp-1 text-[10px] text-slate-500">{{ $package->description }}</p>
                                    @endif
                                </td>

                                <td>
                                    @if ($package->unit_type)
                                        <x-badge variant="sky">{{ $package->unit_type }}</x-badge>
                                    @else
                                        <x-badge variant="violet">Semua Tipe</x-badge>
                                    @endif
                                </td>

                                <td class="tabular text-center text-xs text-slate-300">{{ $package->duration_minutes }} mnt</td>
                                <td class="tabular text-right text-xs font-bold text-white">{{ \App\Support\Money::format($package->price, false) }}</td>

                                <td class="tabular text-right text-xs text-slate-500">
                                    {{ \App\Support\Money::format($package->price / max(1, $package->duration_minutes) * 60, false) }}
                                </td>

                                <td class="tabular text-center text-xs text-slate-500">{{ $package->sort_order }}</td>

                                <td class="text-center">
                                    <x-badge :variant="$package->is_active ? 'emerald' : 'slate'" dot>
                                        {{ $package->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('owner.rate-packages.edit', $package) }}" class="btn-subtle text-[11px]">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            Ubah
                                        </a>

                                        @if ($package->is_active)
                                            <form method="POST" action="{{ route('owner.rate-packages.destroy', $package) }}"
                                                  onsubmit="return confirm('Nonaktifkan paket {{ $package->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-subtle text-[11px] text-rose-400/70 hover:text-rose-300">
                                                    <x-icon name="trash-2" class="h-3.5 w-3.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-xs text-slate-600">
                                    Belum ada paket sewa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($packages->hasPages())
                <div class="border-t border-white/5 px-5 py-3">{{ $packages->links() }}</div>
            @endif
        </div>
    </div>
@endsection
