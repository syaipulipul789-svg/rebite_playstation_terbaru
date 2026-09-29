@php($footerUser = $user ?? auth()->user())

<div class="shrink-0 border-t border-white/5 p-3">
    <div class="flex items-center gap-3 rounded-xl px-2 py-2">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-slate-700 to-slate-800 text-xs font-bold text-slate-200">
            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($footerUser?->name ?? 'U', 0, 2)) }}
        </span>

        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-200">{{ $footerUser?->name }}</p>
            <p class="truncate text-xs text-slate-500">@{{ $footerUser?->username }}</p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 transition hover:bg-rose-500/10 hover:text-rose-400"
                title="Keluar"
            >
                <x-icon name="log-out" class="h-4 w-4" />
            </button>
        </form>
    </div>
</div>
