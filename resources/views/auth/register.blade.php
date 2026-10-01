@extends('layouts.guest')

@section('title', 'Daftar Akun Pelanggan')

@section('content')
    <div class="mb-6">
        <h2 class="text-lg font-bold text-white">Daftar akun pelanggan</h2>
        <p class="mt-1 text-sm text-slate-500">
            Cukup pakai nomor WhatsApp. Nomor ini juga jadi kontak kasir saat mengonfirmasi booking.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('register') }}"
        class="space-y-4"
        x-data="{ showPassword: false }"
    >
        @csrf

        <x-input
            name="name"
            label="Nama Lengkap"
            :value="old('name')"
            autocomplete="name"
            autofocus
            required
        />

        <div>
            <x-input
                name="phone"
                label="Nomor WhatsApp"
                type="tel"
                :value="old('phone')"
                autocomplete="tel"
                placeholder="081234567890"
                required
            />

            @error('phone')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror

            <p class="mt-1 text-xs text-slate-600">
                Boleh diisi format apa saja (<code>0812…</code>, <code>+62 812…</code>, <code>62812…</code>) —
                akan dinormalkan jadi satu nomor.
            </p>
        </div>

        <div class="relative">
            <x-input
                name="password"
                label="Password"
                x-bind:type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                required
            />

            <button
                type="button"
                x-on:click="showPassword = !showPassword"
                class="absolute right-3 top-[34px] grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-white/5 hover:text-slate-300"
                tabindex="-1"
                aria-label="Tampilkan password"
            >
                <x-icon name="eye" class="h-4 w-4" />
            </button>
        </div>

        <div>
            <x-input
                name="password_confirmation"
                label="Ulangi Password"
                x-bind:type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                required
            />

            @error('password')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary w-full">
            <x-icon name="user-plus" class="h-4 w-4" />
            Daftar Sekarang
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-semibold text-brand-400 hover:text-brand-300">Masuk di sini</a>
    </p>

    <p class="mt-4 rounded-xl border border-white/5 bg-ink-900/60 p-4 text-center text-xs text-slate-600">
        Sudah jadi kasir atau owner?
        <a href="{{ route('login') }}" class="font-semibold text-slate-400 hover:text-slate-300">Masuk lewat username</a>.
    </p>
@endsection