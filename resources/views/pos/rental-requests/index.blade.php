@extends('layouts.app')

@use('App\Enums\RentalRequestStatus', 'RentalRequestStatus')
@use('App\Support\Money', 'Money')

@section('title', 'Permintaan Sewa (POS)')
@section('page-title', 'Permintaan Sewa')
@section('page-subtitle', 'Kelola permintaan sewa dari pelanggan. Sistemnya sama seperti booking.')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <span class="badge bg-amber-500/20 text-amber-300 ring-1 ring-inset ring-amber-500/40">Menunggu: {{ $pendingCount }}</span>
            <a href="{{ route('pos.index') }}" class="btn-subtle">Kembali ke POS</a>
        </div>

        <div class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4">
                <div>
                    <h2 class="text-sm font-bold text-white">Daftar Permintaan Sewa</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Permintaan dari pelanggan masuk sebagai PENDING. Setelah disetujui, slot unit terkunci
                        dan jadwalnya tampil <span class="font-semibold text-brand-300">Dipesan</span> bagi pelanggan lain.
                    </p>
                </div>
            </header>

            @if ($rentalRequests->isEmpty())
                <div class="px-6 py-16 text-center">
                    <x-icon name="inbox" class="mx-auto h-10 w-10 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada permintaan sewa.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table-compact">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Pelanggan</th>
                                <th>Unit</th>
                                <th>Jadwal</th>
                                <th>Paket</th>
                                <th class="text-right">Total</th>
                                <th class="text-center">Status</th>
                                <th>Dikonfirmasi Oleh</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rentalRequests as $req)
                                <tr>
                                    <td class="tabular text-xs font-bold text-brand-300">{{ $req->rentalRequestCode() }}</td>

                                    <td class="text-xs">
                                        <p class="font-semibold text-slate-200">{{ $req->customer_name }}</p>
                                        <p class="tabular text-slate-500">{{ $req->customer_phone }}</p>
                                    </td>

                                    <td class="text-xs">
                                        <p class="font-semibold text-slate-200">{{ $req->unit?->name }}</p>
                                        <p class="tabular text-slate-500">{{ $req->unit?->code }}</p>
                                    </td>

                                    <td class="tabular text-xs text-slate-500">
                                        {{ $req->start_time->format('d M H:i') }} → {{ $req->end_time->format('d M H:i') }}
                                        <span class="text-slate-600">({{ $req->duration_minutes }}m)</span>
                                    </td>

                                    <td class="text-xs text-slate-400">{{ $req->package_name ?? '-' }}</td>

                                    <td class="tabular text-right text-xs font-bold text-white">
                                        {{ Money::format($req->total_price, false) }}
                                    </td>

                                    <td class="text-center">
                                        <span class="badge {{ $req->status->badgeClass() }}">{{ $req->status->label() }}</span>
                                    </td>

                                    <td class="text-xs text-slate-400">
                                        @if ($req->confirmer)
                                            {{ $req->confirmer->name }}
                                            <span class="tabular text-slate-600">{{ $req->confirmed_at?->format('d M H:i') }}</span>
                                        @else
                                            <span class="text-slate-600">-</span>
                                        @endif
                                    </td>

                                    <td class="text-right">
                                        @if ($req->isPending())
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <form method="POST" action="{{ route('pos.rental-requests.confirm', $req) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-success btn-sm">Konfirmasi</button>
                                                </form>
                                                <form method="POST" action="{{ route('pos.rental-requests.cancel', $req) }}" onsubmit="return confirm('Batalkan permintaan sewa ini?');">
                                                    @csrf
                                                    <button type="submit" class="btn-danger btn-sm">Batal</button>
                                                </form>
                                            </div>
                                        @elseif ($req->isConfirmed())
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <form method="POST" action="{{ route('pos.rental-requests.complete', $req) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-success btn-sm">Selesaikan</button>
                                                </form>
                                                <form method="POST" action="{{ route('pos.rental-requests.cancel', $req) }}" onsubmit="return confirm('Batalkan permintaan sewa ini?');">
                                                    @csrf
                                                    <button type="submit" class="btn-danger btn-sm">Batal</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-600">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-xs text-slate-600">
                                        Belum ada permintaan sewa.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-white/5 px-5 py-4">
                    {{ $rentalRequests->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection