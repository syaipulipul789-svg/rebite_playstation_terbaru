@extends('layouts.app')

@use('App\Enums\OrderStatus', 'OrderStatus')
@use('App\Enums\PaymentMethod', 'PaymentMethod')
@use('App\Support\Money', 'Money')

@section('title', 'Pesanan Barcode')
@section('page-title', 'Pesanan Barcode Pelanggan')
@section('page-subtitle', 'Pesanan menu hasil pemindaian barcode — terima pembayaran di sini')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card
                label="Menunggu Bayar"
                :value="$awaitingCount"
                hint="Pesanan dikirim pelanggan"
                icon="clock"
                tone="sky"
            />

            <x-stat-card
                label="Draft Pelanggan"
                :value="$draftCount"
                hint="Belum dikirim ke kasir"
                icon="pencil"
                tone="amber"
            />

            <x-stat-card
                label="Total Terpilih"
                :value="$orders->count()"
                hint="Jumlah pesanan pada filter ini"
                icon="shopping-bag"
                tone="brand"
            />
        </div>

        <div class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([
                        'awaiting' => 'Menunggu Bayar',
                        'paid' => 'Sudah Dibayar',
                        'all' => 'Semua',
                    ] as $value => $label)
                        <a href="{{ route('pos.orders', ['status' => $value]) }}"
                           @class([
                               'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                               'bg-brand-500 text-white' => $filter === $value,
                               'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $filter !== $value,
                           ])>{{ $label }}</a>
                    @endforeach
                </div>

                <a href="{{ route('pos.index') }}" class="btn-subtle">
                    <x-icon name="receipt" class="h-3.5 w-3.5" />
                    Kembali ke Kasir
                </a>
            </header>

            @forelse ($orders as $order)
                <div class="border-b border-white/5 px-5 py-4 last:border-b-0">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        {{-- KIRI: identitas pesanan --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="tabular text-sm font-extrabold tracking-wider text-brand-300">{{ $order->code }}</span>
                                <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                            </div>

                            <p class="mt-1.5 text-sm font-semibold text-slate-200">
                                {{ $order->customer_name }}
                                <span class="tabular font-normal text-slate-500">· {{ $order->customer_phone }}</span>
                            </p>

                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $order->created_at->format('d M Y H:i') }}
                                @if ($order->unit)
                                    · Unit <span class="tabular font-semibold text-slate-400">{{ $order->unit->code }}</span>
                                @endif
                                @if ($order->booking)
                                    · Booking <span class="tabular font-semibold text-slate-400">{{ $order->booking->bookingCode() }}</span>
                                @endif
                                @if ($order->user && $order->settled_at)
                                    · {{ $order->user->name }} · {{ $order->settled_at->format('H:i') }}
                                @endif
                            </p>
                        </div>

                        {{-- KANAN: total & aksi --}}
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <p class="tabular text-lg font-extrabold text-white">{{ Money::format($order->total_price) }}</p>
                            <p class="text-[10px] text-slate-500">{{ $order->itemCount() }} item</p>

                            @if ($order->status === OrderStatus::PLACED)
                                <form method="POST" action="{{ route('pos.orders.settle', $order) }}"
                                      class="flex flex-wrap items-center justify-end gap-1.5">
                                    @csrf

                                    <select name="payment_method" class="input w-auto py-1.5 text-xs" required>
                                        @foreach (PaymentMethod::options() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    <button type="submit" class="btn-success btn-sm">
                                        <x-icon name="banknote" class="h-3.5 w-3.5" />
                                        Terima Bayar
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('pos.orders.cancel', $order) }}"
                                      onsubmit="return confirm('Batalkan pesanan {{ $order->code }}? Stok akan dikembalikan.');">
                                    @csrf
                                    <input type="hidden" name="reason" value="Dibatalkan kasir">
                                    <button type="submit" class="btn-subtle text-[11px] text-rose-400/70 hover:text-rose-300">
                                        <x-icon name="x-circle" class="h-3.5 w-3.5" />
                                        Batalkan
                                    </button>
                                </form>
                            @elseif ($order->status === OrderStatus::COMPLETED)
                                <span class="badge {{ $order->payment_method->badgeClass() }}">
                                    {{ $order->payment_method->label() }}
                                </span>
                            @else
                                <span class="text-xs text-slate-600">—</span>
                            @endif
                        </div>
                    </div>

                    {{-- RINCIAN ITEM --}}
                    @if ($order->items->isNotEmpty())
                        <ul class="mt-3 space-y-1 rounded-xl bg-ink-900/60 px-3 py-2.5">
                            @foreach ($order->items as $item)
                                <li class="flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate text-slate-300">
                                        {{ $item->product?->name }}
                                        <span class="tabular text-slate-500">× {{ $item->qty }}</span>
                                    </span>
                                    <span class="tabular shrink-0 font-semibold text-slate-200">
                                        {{ Money::format($item->subtotal) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($order->notes)
                        <p class="mt-2 text-xs text-slate-500">Catatan: {{ $order->notes }}</p>
                    @endif
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <x-icon name="shopping-bag" class="mx-auto h-10 w-10 text-slate-700" />
                    <p class="mt-4 text-sm font-semibold text-slate-400">Belum ada pesanan barcode.</p>
                    <p class="mt-1 text-xs text-slate-600">
                        Pesanan muncul di sini setelah pelanggan memindai barcode di halaman pesanan.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
