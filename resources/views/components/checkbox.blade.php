@props([
    'for' => null,
    'checked' => false,
    'name' => null,
])

@php($checkboxName = $name ?? $for)

<label @if ($for) for="{{ $for }}" @endif class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-slate-300">
    <input
        type="checkbox"
        @if ($for) id="{{ $for }}" @endif
        name="{{ $checkboxName }}"
        value="1"
        @checked((string) old($checkboxName, $checked ? '1' : '0') === '1')
        {{ $attributes->except('name')->merge(['class' => 'h-4 w-4 rounded border-white/20 bg-ink-900 text-brand-500 focus:ring-2 focus:ring-brand-500/40']) }}
    />
    {{ $slot }}
</label>
