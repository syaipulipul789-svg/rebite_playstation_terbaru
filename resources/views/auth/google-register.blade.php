@extends('layouts.guest')

@section('title', 'Lengkapi Akun Google')

@section('content')
    <h2 class="text-lg font-bold text-white">Lengkapi akun pelanggan</h2>
    <p class="mt-2 text-sm text-slate-400">
        Google <strong class="text-white">{{ $googleAccount['email'] }}</strong> berhasil diverifikasi.
        Masukkan nomor WhatsApp agar kasir bisa menghubungi Anda terkait booking.
    </p>

    <form method="POST" action="{{ route('google.register.store') }}" class="mt-6 space-y-4">
        @csrf
        <x-input name="phone" type="tel" label="Nomor WhatsApp" :value="old('phone')" autocomplete="tel" placeholder="081234567890" required />
        <p class="text-xs text-slate-500">Sudah punya akun dengan nomor ini? Masuk dengan nomor WhatsApp lalu hubungkan Google lewat Pengaturan Akun.</p>
        <button type="submit" class="btn-primary w-full">Buat Akun Pelanggan</button>
    </form>

    <a href="{{ route('login') }}" class="btn-subtle mt-4 w-full">Kembali ke halaman masuk</a>
@endsection
