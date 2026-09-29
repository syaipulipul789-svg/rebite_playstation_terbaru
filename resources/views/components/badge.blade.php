@props([
    'variant' => 'default',
    'dot' => null,
])

@php
    $variants = [
        'emerald' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
        'rose' => 'bg-rose-500/15 text-rose-300 ring-1 ring-inset ring-rose-500/30',
        'amber' => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
        'sky' => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
        'violet' => 'bg-violet-500/15 text-violet-300 ring-1 ring-inset ring-violet-500/30',
        'cyan' => 'bg-cyan-500/15 text-cyan-300 ring-1 ring-inset ring-cyan-500/30',
        'slate' => 'bg-slate-500/15 text-slate-300 ring-1 ring-inset ring-slate-500/30',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . ($variants[$variant] ?? $variants['slate'])]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    @endif

    {{ $slot }}
</span>
