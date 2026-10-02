@extends('layouts.app')

@use('App\Enums\PaymentMethod')
@use('App\Enums\UnitStatus')

@section('title', 'Kelola Unit')
@section('page-title', 'Kelola Unit')
@section('page-subtitle', 'Master data konsol, tarif per jam, dan status operasional')

@section('content')
    <div class="space-y-5">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-1.5">
                @foreach ([
                    '' => 'Semua',
                    'READY' => 'Ready',
                    'BUSY' => 'Berjalan',
                    'MAINTENANCE' => 'Servis',
                ] as $value => $label)
                    <a
                        href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            'bg-brand-500 text-white' => ($filters['status'] ?? '') === $value,
                            'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => ($filters['status'] ?? '') !== $value,
                        ])
                    >{{ $label }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('owner.units.qr') }}" class="btn-subtle">
                    <x-icon name="qr-code" class="h-4 w-4" />
                    QR Meja
                </a>

                <a href="{{ route('owner.units.create') }}" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Tambah Unit
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('owner.units.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[200px] flex-1">
                <label for="q" class="label">Cari Unit</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode atau nama unit…" class="input">
            </div>

            <div class="min-w-[160px] flex-1">
                <label for="type" class="label">Tipe Konsol</label>
                <select id="type" name="type" class="input">
                    <option value="">Semua tipe</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">

            <button type="submit" class="btn-primary">
                <x-icon name="search" class="h-4 w-4" />
                Terapkan
            </button>

            @if (array_filter($filters))
                <a href="{{ route('owner.units.index') }}" class="btn-ghost">
                    <x-icon name="rotate-ccw" class="h-4 w-4" />
                    Reset
                </a>
            @endif
        </form>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Tipe</th>
                            <th>Lokasi</th>
                            <th class="text-right">Tarif / Jam</th>
                            <th class="text-center">Selesai</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($units as $unit)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span @class([
                                            'grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[10px] font-bold',
                                            'bg-emerald-500/15 text-emerald-300' => $unit->status === UnitStatus::READY,
                                            'bg-rose-500/15 text-rose-300' => $unit->status === UnitStatus::BUSY,
                                            'bg-amber-500/15 text-amber-300' => $unit->status === UnitStatus::MAINTENANCE,
                                        ])>
                                            <x-icon name="monitor" class="h-4 w-4" />
                                        </span>

                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-200">{{ $unit->name }}</p>
                                            <p class="tabular truncate text-[10px] text-slate-500">{{ $unit->code }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-xs text-slate-400">{{ $unit->type }}</td>
                                <td class="text-xs text-slate-500">{{ $unit->location ?? '—' }}</td>
                                <td class="tabular text-right text-xs font-semibold text-white">
                                    {{ \App\Support\Money::format($unit->hourly_rate, false) }}
                                </td>
                                <td class="tabular text-center text-xs text-slate-400">{{ $unit->completed_sessions }}</td>

                                <td class="text-center">
                                    <x-badge :variant="$unit->status->badgeVariant()" dot>{{ $unit->status->label() }}</x-badge>
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @unless ($unit->status === UnitStatus::BUSY)
                                            <form method="POST" action="{{ route('owner.units.status', $unit) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="{{ $unit->status === UnitStatus::MAINTENANCE ? 'READY' : 'MAINTENANCE' }}"
                                                >
                                                <button type="submit" class="btn-subtle text-[11px]"
                                                        title="{{ $unit->status === UnitStatus::MAINTENANCE ? 'Tandai siap' : 'Tandai servis' }}">
                                                    <x-icon name="wrench" class="h-3.5 w-3.5" />
                                                    {{ $unit->status === UnitStatus::MAINTENANCE ? 'Siapkan' : 'Servis' }}
                                                </button>
                                            </form>
                                        @endunless

                                        <a href="{{ route('owner.units.edit', $unit) }}" class="btn-subtle text-[11px]">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            Ubah
                                        </a>

                                        <form
                                            method="POST"
                                            action="{{ route('owner.units.destroy', $unit) }}"
                                            onsubmit="return confirm('Hapus unit {{ $unit->code }}? Riwayat sewa tidak ikut terhapus.')"
                                        >
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
                                    Tidak ada unit yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($units->hasPages())
                <div class="border-t border-white/5 px-5 py-3">{{ $units->links() }}</div>
            @endif
        </div>
    </div>
@endsection
