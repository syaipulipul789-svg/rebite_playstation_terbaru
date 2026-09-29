@extends('layouts.app')

@section('title', 'Kelola Pengguna')
@section('page-title', 'Kelola Pengguna')
@section('page-subtitle', 'Akun Owner dan kasir yang punya akses ke aplikasi')

@section('content')
    <div class="space-y-5">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('owner.users.index') }}"
                   @class([
                       'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                       'bg-brand-500 text-white' => empty($filters['role']),
                       'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => ! empty($filters['role']),
                   ])>Semua</a>

                @foreach ($roleOptions as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['role' => $value, 'page' => null]) }}"
                       @class([
                           'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                           'bg-brand-500 text-white' => ($filters['role'] ?? '') === $value,
                           'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => ($filters['role'] ?? '') !== $value,
                       ])>{{ $label }}</a>
                @endforeach
            </div>

            <a href="{{ route('owner.users.create') }}" class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Akun
            </a>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Role</th>
                            <th class="text-center">Total Shift</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($users as $managedUser)
                            @php($isSelf = auth()->user()->is($managedUser))

                            <tr @class(['opacity-50' => ! $managedUser->is_active])>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-slate-700 to-slate-800 text-[10px] font-bold text-slate-200">
                                            {{ strtoupper(substr($managedUser->name, 0, 2)) }}
                                        </span>

                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-200">
                                                {{ $managedUser->name }}
                                                @if ($isSelf)
                                                    <span class="ml-1 text-[10px] font-normal text-brand-300">(Anda)</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-[10px] text-slate-500">@{{ $managedUser->username }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <x-badge :variant="$managedUser->role->isOwner() ? 'violet' : 'sky'">
                                        {{ $managedUser->role->label() }}
                                    </x-badge>
                                </td>

                                <td class="tabular text-center text-xs text-slate-400">{{ $managedUser->shifts_count }}</td>

                                <td class="text-center">
                                    <x-badge :variant="$managedUser->is_active ? 'emerald' : 'slate'" dot>
                                        {{ $managedUser->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('owner.users.edit', $managedUser) }}" class="btn-subtle text-[11px]">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            Ubah
                                        </a>

                                        @unless ($isSelf)
                                            <form method="POST" action="{{ route('owner.users.destroy', $managedUser) }}"
                                                  onsubmit="return confirm('Hapus akun {{ $managedUser->username }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-subtle text-[11px] text-rose-400/70 hover:text-rose-300">
                                                    <x-icon name="trash-2" class="h-3.5 w-3.5" />
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-xs text-slate-600">Belum ada pengguna.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-white/5 px-5 py-3">{{ $users->links() }}</div>
            @endif
        </div>
    </div>
@endsection
