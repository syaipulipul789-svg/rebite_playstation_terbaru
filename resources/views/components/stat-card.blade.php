@props([
    'label',
    'value',
    'icon' => 'wallet',
    'variant' => 'sky',
    'hint' => null,
    'trend' => null,
])

@php
    $tones = [
        'sky' => ['icon' => 'bg-sky-500/15 text-sky-300', 'value' => 'text-white'],
        'emerald' => ['icon' => 'bg-emerald-500/15 text-emerald-300', 'value' => 'text-white'],
        'rose' => ['icon' => 'bg-rose-500/15 text-rose-300', 'value' => 'text-rose-300'],
        'amber' => ['icon' => 'bg-amber-500/15 text-amber-300', 'value' => 'text-amber-300'],
        'violet' => ['icon' => 'bg-violet-500/15 text-violet-300', 'value' => 'text-white'],
        'cyan' => ['icon' => 'bg-cyan-500/15 text-cyan-300', 'value' => 'text-white'],
    ];
    $tone = $tones[$variant] ?? $tones['sky'];
@endphp

<div {{ $attributes->merge(['class' => 'card card-hover p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>

            <p class="tabular mt-2 truncate text-2xl font-bold {{ $tone['value'] }}">{{ $value }}</p>

            @if ($hint)
                <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
            @endif
        </div>

        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $tone['icon'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>

    @isset($footer)
        <div class="mt-4 border-t border-white/5 pt-3">
            {{ $footer }}
        </div>
    @endisset
</div>
