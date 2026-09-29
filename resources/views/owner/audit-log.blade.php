@extends('layouts.app')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')
@section('page-subtitle', 'Jejak digital setiap aksi kasir, shift, dan perubahan master data')

@section('content')
    <div class="space-y-5">

        {{-- ================= FILTER ================= --}}
        <form method="GET" action="{{ route('owner.audit-log') }}" class="card flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[210px] flex-1">
                <label for="event" class="label">Jenis Aktivitas</label>
                <select id="event" name="event" class="input">
                    <option value="">Semua aktivitas</option>
                    @foreach ($eventOptions as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>
                            {{ str($event)->replace('_', ' ')->headline() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[190px] flex-1">
                <label for="user_id" class="label">Kasir</label>
                <select id="user_id" name="user_id" class="input">
                    <option value="">Semua kasir</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" @selected((int) ($filters['user_id'] ?? 0) === $cashier->id)>
                            {{ $cashier->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">
                <x-icon name="search" class="h-4 w-4" />
                Filter
            </button>

            @if (array_filter($filters))
                <a href="{{ route('owner.audit-log') }}" class="btn-ghost">
                    <x-icon name="rotate-ccw" class="h-4 w-4" />
                    Reset
                </a>
            @endif
        </form>

        {{-- ================= DAFTAR LOG ================= --}}
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Aktor</th>
                            <th>Jenis</th>
                            <th>Keterangan</th>
                            <th class="text-right">Detail</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $tone = match (true) {
                                    str_contains($log->event, 'COMPLETED') => 'emerald',
                                    str_contains($log->event, 'CANCELLED') => 'rose',
                                    str_contains($log->event, 'SHIFT_CLOSED') => ($log->context['discrepancy'] ?? 0) != 0 ? 'amber' : 'sky',
                                    str_contains($log->event, 'STARTED') => 'sky',
                                    default => 'slate',
                                };
                            @endphp

                            <tr>
                                <td class="whitespace-nowrap">
                                    <p class="tabular text-xs font-semibold text-slate-300">
                                        {{ $log->created_at->format('d M Y') }}
                                    </p>
                                    <p class="tabular text-[10px] text-slate-600">
                                        {{ $log->created_at->format('H:i:s') }} · {{ $log->created_at->diffForHumans(short: true) }}
                                    </p>
                                </td>

                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gradient-to-br from-slate-700 to-slate-800 text-[10px] font-bold text-slate-200">
                                            {{ strtoupper(substr($log->user?->name ?? 'S', 0, 2)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-200">{{ $log->user?->name ?? 'Sistem' }}</p>
                                            <p class="truncate text-[10px] text-slate-600">@{{ $log->user?->username ?? 'system' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <x-badge :variant="$tone">
                                        {{ str($log->event)->replace('_', ' ')->headline() }}
                                    </x-badge>
                                </td>

                                <td class="max-w-md">
                                    <p class="text-xs leading-relaxed text-slate-300">{{ $log->description }}</p>

                                    @if ($log->note)
                                        <p class="mt-1 rounded-md bg-amber-500/10 px-2 py-1 text-[11px] text-amber-200/80">
                                            Catatan: {{ $log->note }}
                                        </p>
                                    @endif
                                </td>

                                <td class="text-right">
                                    @if (! empty($log->context))
                                        <button
                                            type="button"
                                            x-data
                                            x-on:click="$store.toast.info(@js($log->context), 'Konteks Audit')"
                                            class="btn-subtle text-[11px]"
                                        >
                                            <x-icon name="eye" class="h-3.5 w-3.5" />
                                            Lihat
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-700">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-14 text-center text-xs text-slate-600">
                                    Belum ada aktivitas yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-white/5 px-5 py-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
