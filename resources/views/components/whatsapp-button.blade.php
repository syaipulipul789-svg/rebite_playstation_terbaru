@props([
    'booking',
    'label' => 'Kirim WA Konfirmasi',
])

{{-- Tautan wa.me membuka chat WhatsApp pelanggan dengan pesan yang sudah
     terisi. Nomor yang tidak bisa dipakai WhatsApp sengaja ditampilkan
     sebagai catatan, bukan link mati, supaya kasir tahu apa yang salah. --}}
@if ($waUrl = $booking->whatsappApprovalUrl())
    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="btn-subtle btn-sm">
        <x-icon name="message-circle" class="h-3.5 w-3.5" />
        {{ $label }}
    </a>
@else
    <span
        class="text-[10px] font-medium text-slate-500"
        title="Nomor pelanggan bukan nomor seluler Indonesia yang bisa dibuka di WhatsApp."
    >Nomor tidak valid</span>
@endif
