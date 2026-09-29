<header class="sticky top-0 z-20 border-b border-white/5 bg-ink-950/85 backdrop-blur">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">

        <button
            type="button"
            x-on:click="sidebar = true"
            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-white/5 hover:text-white lg:hidden"
            aria-label="Buka menu"
        >
            <x-icon name="menu" class="h-5 w-5" />
        </button>

        <div class="min-w-0 flex-1">
            <h1 class="truncate text-base font-bold text-white sm:text-lg">
                @yield('page-title', 'Dashboard')
            </h1>

            @hasSection('page-subtitle')
                <p class="truncate text-xs text-slate-500">@yield('page-subtitle')</p>
            @endif
        </div>

        <div x-data="clock()" class="hidden text-right sm:block">
            <p class="tabular text-sm font-bold text-white" x-text="time"></p>
            <p class="text-xs capitalize text-slate-500" x-text="date"></p>
        </div>

        <span class="hidden h-8 w-px bg-white/5 sm:block"></span>

        <x-badge :variant="auth()->user()->role->isOwner() ? 'violet' : 'cyan'" dot>
            {{ auth()->user()->role->label() }}
        </x-badge>
    </div>
</header>
