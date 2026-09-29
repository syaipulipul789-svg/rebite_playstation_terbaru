<div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-4">
    <a href="{{ auth()->user()->isOwner() ? route('owner.dashboard') : route('units.index') }}"
       class="flex items-center gap-2.5">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-neon-500 text-white shadow-lg shadow-brand-500/25">
            <x-icon name="zap" class="h-5 w-5" />
        </span>

        <span class="leading-tight">
            <span class="block text-sm font-extrabold tracking-tight text-white">Rebite</span>
            <span class="block text-[10px] font-semibold uppercase tracking-widest text-slate-500">Playstation</span>
        </span>
    </a>

    <button
        type="button"
        x-on:click="sidebar = false"
        class="ml-auto grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-white/5 hover:text-white lg:hidden"
        aria-label="Tutup menu"
    >
        <x-icon name="x" class="h-5 w-5" />
    </button>
</div>
