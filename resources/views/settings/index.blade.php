@extends(auth()->user()->isCustomer() ? 'layouts.guest' : 'layouts.app')

@section('title', 'Pengaturan Akun')
@section('page-title', 'Pengaturan Akun')
@section('page-subtitle', 'Keamanan akun dan cara masuk')

@section('content')
    <div class="mx-auto max-w-xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-white">Pengaturan Akun</h2>
                <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->name }}</p>
            </div>
            <a href="{{ route(auth()->user()->landingRoute()) }}" class="btn-subtle text-xs">Kembali</a>
        </div>

        @if (session('status') === 'password-updated')
            <p class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-300">Password berhasil diubah.</p>
        @endif

        @if (auth()->user()->isStaff())
            <div class="card p-5 sm:p-6">
                <h3 class="font-bold text-white">Ubah Password</h3>
                <p class="mt-1 text-sm text-slate-500">Anda bisa mengubah password kapan saja. Masukkan password saat ini untuk mengonfirmasi.</p>

                <form method="POST" action="{{ route('user-password.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')
                    <x-input name="current_password" type="password" label="Password Saat Ini" autocomplete="current-password" required />
                    @error('current_password', 'updatePassword')
                        <p class="text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                    <x-input name="password" type="password" label="Password Baru" autocomplete="new-password" required />
                    @error('password', 'updatePassword')
                        <p class="text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                    <x-input name="password_confirmation" type="password" label="Ulangi Password Baru" autocomplete="new-password" required />
                    <button type="submit" class="btn-primary w-full">Simpan Password Baru</button>
                </form>
            </div>
        @endif

        <div class="card p-5 sm:p-6">
            <h3 class="font-bold text-white">Masuk dengan Google</h3>
            @if (auth()->user()->google_id !== null)
                <p class="mt-2 text-sm text-emerald-300">Akun Google sudah terhubung. Anda dapat menggunakan tombol Masuk dengan Google.</p>
            @elseif (filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
                <p class="mt-2 text-sm text-slate-400">Hubungkan Google ke akun yang sedang masuk. Untuk keamanan, masukkan password akun ini terlebih dahulu.</p>
                <form method="POST" action="{{ route('google.link') }}" class="mt-4 space-y-4">
                    @csrf
                    <x-input name="current_password" type="password" label="Password Saat Ini" autocomplete="current-password" required />
                    <button type="submit" class="btn-primary w-full">Hubungkan Akun Google</button>
                </form>
            @else
                <p class="mt-2 text-sm text-amber-300">Login Google belum tersedia. Pengelola perlu mengisi kredensial Google terlebih dahulu.</p>
            @endif
        </div>
    </div>
@endsection
