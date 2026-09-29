@extends('layouts.guest')

@section('title', 'Lupa Password')

@section('content')
    <div class="mb-6">
        <h2 class="text-lg font-bold text-white">Atur ulang password</h2>
        <p class="mt-1 text-sm text-slate-500">
            Masukkan username terdaftar. Kami kirim tautan reset ke email akun Anda.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-input
            name="username"
            label="Username"
            :value="old('username')"
            autocomplete="username"
            autofocus
            required
        />

        <button type="submit" class="btn-primary w-full">
            <x-icon name="key-round" class="h-4 w-4" />
            Kirim Tautan Reset
        </button>

        <a href="{{ route('login') }}" class="btn-subtle w-full">
            <x-icon name="chevron-left" class="h-4 w-4" />
            Kembali ke halaman masuk
        </a>
    </form>
@endsection
