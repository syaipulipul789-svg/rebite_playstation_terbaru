@extends('layouts.app')

@section('title', $managedUser->exists ? 'Ubah Akun' : 'Tambah Akun')
@section('page-title', $managedUser->exists ? 'Ubah Akun ' . $managedUser->username : 'Tambah Akun Baru')
@section('page-subtitle', 'Kasir wajib membuka shift sebelum bisa menjalankan transaksi')

@section('content')
    @php($isSelf = auth()->user()->is($managedUser))

    <div class="mx-auto max-w-2xl">
        <form method="POST"
              action="{{ $managedUser->exists ? route('owner.users.update', $managedUser) : route('owner.users.store') }}"
              class="card space-y-5 p-5">
            @csrf
            @if ($managedUser->exists) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input
                    label="Nama Lengkap"
                    name="name"
                    :value="old('name', $managedUser->name)"
                    placeholder="Budi Santoso"
                    required
                    maxlength="100"
                />

                <x-input
                    label="Username"
                    name="username"
                    :value="old('username', $managedUser->username)"
                    placeholder="kasir03"
                    required
                    maxlength="50"
                />
            </div>

            <x-input
                label="Email"
                name="email"
                type="email"
                :value="old('email', $managedUser->email)"
                placeholder="nama@rebite.id"
                maxlength="150"
                hint="Dipakai untuk tautan reset password. Boleh dikosongkan."
            />

            <x-select
                label="Role"
                name="role"
                :value="old('role', $managedUser->role?->value ?? 'KASIR')"
                :options="$roleOptions"
                required
                @if ($isSelf) disabled @endif
            />

            @if ($isSelf)
                <input type="hidden" name="role" value="{{ $managedUser->role->value }}">
                <p class="-mt-3 text-xs text-amber-300/80">
                    Role akun sendiri tidak bisa diubah dari halaman ini.
                </p>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input
                    :label="$managedUser->exists ? 'Password Baru (opsional)' : 'Password'"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    :required="! $managedUser->exists"
                    hint="Minimal 8 karakter."
                />

                <x-input
                    label="Ulangi Password"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :required="! $managedUser->exists"
                />
            </div>

            <div>
                {{-- Atribut `disabled` dihitung di luar tag komponen karena Blade
                     tidak bisa mengompilasi directive kondisional di dalam tag
                     component yang multi-baris. --}}
                <x-checkbox
                    name="is_active"
                    :checked="old('is_active', $managedUser->is_active ?? true)"
                    :disabled="$isSelf"
                >
                    Akun Aktif
                </x-checkbox>

                @if ($isSelf)
                    <input type="hidden" name="is_active" value="1">
                    <p class="mt-1.5 pl-7 text-xs text-amber-300/80">
                        Akun sendiri tidak bisa dinonaktifkan.
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap gap-3 border-t border-white/5 pt-4">
                <button type="submit" class="btn-primary flex-1">
                    <x-icon name="save" class="h-4 w-4" />
                    {{ $managedUser->exists ? 'Simpan Perubahan' : 'Buat Akun' }}
                </button>

                <a href="{{ route('owner.users.index') }}" class="btn-ghost">Batal</a>
            </div>
        </form>
    </div>
@endsection
