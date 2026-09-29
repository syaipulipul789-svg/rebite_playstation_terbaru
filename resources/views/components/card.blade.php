@props([
    'title' => null,
    'icon' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if ($title)
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4">
            <div class="flex items-center gap-2.5">
                @if ($icon)
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-slate-400">
                        <x-icon :name="$icon" class="h-4 w-4" />
                    </span>
                @endif

                <div>
                    <h2 class="text-sm font-semibold text-slate-100">{{ $title }}</h2>
                    @if ($description)
                        <p class="text-xs text-slate-500">{{ $description }}</p>
                    @endif
                </div>
            </div>

            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}
</section>
