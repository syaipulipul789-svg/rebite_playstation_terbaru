@extends('layouts.app')

@use('App\Enums\BookingStatus', 'BookingStatus')
@use('App\Enums\PaymentMethod', 'PaymentMethod')

@section('title', 'Daftar Booking')
@section('page-title', 'Daftar Booking')
@section('page-subtitle', 'Reservasi online pelanggan — setujui atau batalkan booking yang masuk')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card
                label="Menunggu Konfirmasi"
                :value="$pendingCount"
                hint="Booking status pending"
                icon="clock"
                tone="amber"
            />

            <x-stat-card
                label="Dikonfirmasi"
                :value="$bookings->where('status', BookingStatus::CONFIRMED)->count()"
                hint="Booking yang sudah disetujui"
                icon="check-circle-2"
                tone="sky"
            />

            <x-stat-card
                label="Total Booking"
                :value="$bookings->count()"
                hint="Seluruh riwayat reservasi"
                icon="calendar-days"
                tone="brand"
            />
        </div>

        <div class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4">
                <div>
                    <h2 class="text-sm font-bold text-white">Booking Reservasi Online</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Setelah disetujui, jadwal unit tampil <span class="font-semibold text-brand-300">Terisi</span> bagi pelanggan lain.
                        Sesi rental dan hitung mundur dimulai saat jam booking tiba.
                        Tombol <span class="font-semibold text-slate-400">Kirim WA Konfirmasi</span> membuka WhatsApp
                        pelanggan dengan pesan konfirmasi yang sudah terisi otomatis.
                    </p>
                </div>

                <a href="{{ route('pos.index') }}" class="btn-subtle">
                    <x-icon name="receipt" class="h-3.5 w-3.5" />
                    Kembali ke Kasir
                </a>
            </header>

            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Unit</th>
                            <th>Pelanggan</th>
                            <th>Jadwal</th>
                            <th>Durasi</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($bookings as $booking)
                            <tr>
                                <td class="tabular text-xs font-bold text-brand-300">{{ $booking->bookingCode() }}</td>

                                <td>
                                    <p class="text-xs font-semibold text-slate-200">{{ $booking->console->name }}</p>
                                    <p class="tabular text-[10px] text-slate-500">{{ $booking->console->code }}</p>
                                </td>

                                <td class="text-xs">
                                    <p class="font-semibold text-slate-200">{{ $booking->customer_name }}</p>
                                    <p class="tabular text-slate-500">{{ $booking->customer_phone }}</p>
                                </td>

                                <td class="tabular text-xs text-slate-500">
                                    {{ $booking->start_time->format('d M H:i') }} → {{ $booking->end_time->format('d M H:i') }}
                                    @if ($booking->notes)
                                        <br>
                                        <span class="text-slate-600">「{{ $booking->notes }}」</span>
                                    @endif
                                </td>

                                <td class="tabular text-xs text-slate-500">{{ $booking->durationHours() }} jam</td>

                                <td class="tabular text-right text-xs font-bold text-white">
                                    {{ \App\Support\Money::format($booking->total_price, false) }}
                                </td>

                                <td class="text-center">
                                    <span class="badge {{ $booking->status->badgeClass() }}">
                                        {{ $booking->status->label() }}
                                    </span>

                                    @if ($booking->hasRunningSession())
                                        <p class="mt-1 text-[10px] font-semibold text-brand-300">Unit Terisi</p>
                                    @endif
                                </td>

                                <td class="text-right">
                                    @if ($booking->status === BookingStatus::PENDING)
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <form method="POST" action="{{ route('pos.bookings.confirm', $booking) }}">
                                                @csrf
                                                <button type="submit" class="btn-success btn-sm">
                                                    <x-icon name="check" class="h-3.5 w-3.5" />
                                                    Confirm
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('pos.bookings.cancel', $booking) }}"
                                                  onsubmit="return confirm('Batalkan booking {{ $booking->bookingCode() }}?');">
                                                @csrf
                                                <button type="submit" class="btn-danger btn-sm">
                                                    <x-icon name="x-circle" class="h-3.5 w-3.5" />
                                                    Batalkan
                                                </button>
                                            </form>
                                        </div>
                                    @elseif ($booking->status === BookingStatus::CONFIRMED)
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                            {{-- Sesi yang sudah jalan butuh metode pembayaran
                                                 supaya uangnya masuk rekap shift kasir. --}}
                                            <form method="POST" action="{{ route('pos.bookings.complete', $booking) }}"
                                                  class="inline-flex items-center justify-end gap-1.5">
                                                @csrf
                                                <select name="payment_method" class="input w-auto py-1.5 text-xs">
                                                    @foreach (PaymentMethod::options() as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn-success btn-sm">
                                                    <x-icon name="check-circle-2" class="h-3.5 w-3.5" />
                                                    Selesai
                                                </button>
                                            </form>

                                            @unless ($booking->hasRunningSession())
                                                <form method="POST" action="{{ route('pos.bookings.cancel', $booking) }}"
                                                      onsubmit="return confirm('Batalkan booking {{ $booking->bookingCode() }}?');">
                                                    @csrf
                                                    <button type="submit" class="btn-danger btn-sm">
                                                        <x-icon name="x-circle" class="h-3.5 w-3.5" />
                                                        Batalkan
                                                    </button>
                                                </form>
                                            @endunless

                                            {{-- Booking sudah disetujui, jadiKasir bisa
                                                 langsung mengabari pelanggan lewat WA. --}}
                                            <x-whatsapp-button :booking="$booking" />
                                        </div>
                                    @elseif ($booking->status === BookingStatus::COMPLETED)
                                        <x-whatsapp-button :booking="$booking" />
                                    @else
                                        <span class="text-xs text-slate-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-xs text-slate-600">
                                    Belum ada booking reservasi online.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection