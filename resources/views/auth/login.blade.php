@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <div class="mb-6">
        <h2 class="text-lg font-bold text-white">Masuk ke akun Anda</h2>
        <p class="mt-1 text-sm text-slate-500">Gunakan username kasir atau owner yang terdaftar.</p>
    </div>

    <form
        method="POST"
        action="{{ route('login') }}"
        class="space-y-4"
        x-data="{ showPassword: false }"
    >
        @csrf

        <x-input
            name="username"
            label="Username"
            :value="old('username')"
            autocomplete="username"
            autofocus
            required
        />

        <div class="relative">
            <x-input
                name="password"
                label="Password"
                x-bind:type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
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

        <div class="flex items-center justify-between pt-1">
            <x-checkbox name="remember">Ingat saya</x-checkbox>

            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-400 hover:text-brand-300">
                Lupa password?
            </a>
        </div>

        <button type="submit" class="btn-primary w-full">
            <x-icon name="log-in" class="h-4 w-4" />
            Masuk
        </button>
    </form>

    <div class="mt-6 rounded-xl border border-white/5 bg-ink-900/60 p-4">
        <p class="mb-2.5 flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-slate-600">
            <x-icon name="key-round" class="h-3.5 w-3.5" />
            Akun Demo
        </p>

        <div class="space-y-1.5 text-xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-slate-500">Owner</span>
                <code class="rounded bg-white/5 px-2 py-0.5 font-mono text-slate-300">owner / password</code>
            </div>
            <div class="flex items-center justify-between gap-2">
                <span class="text-slate-500">Kasir</span>
                <code class="rounded bg-white/5 px-2 py-0.5 font-mono text-slate-300">kasir01 / password</code>
            </div>
        </div>
    </div>
@endsection
