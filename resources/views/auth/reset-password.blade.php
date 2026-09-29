@extends('layouts.guest')

@section('title', 'Reset Password')

@section('content')
    <div class="mb-6">
        <h2 class="text-lg font-bold text-white">Password baru</h2>
        <p class="mt-1 text-sm text-slate-500">Pastikan password berbeda dari password lama Anda.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input name="email" label="Email" type="email" :value="old('email', $request->input('email'))" readonly />

        <x-input
            name="password"
            label="Password Baru"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-input
            name="password_confirmation"
            label="Konfirmasi Password"
            type="password"
            autocomplete="new-password"
            required
        />

        <button type="submit" class="btn-primary w-full">
            <x-icon name="check" class="h-4 w-4" />
            Simpan Password Baru
        </button>
    </form>
@endsection
