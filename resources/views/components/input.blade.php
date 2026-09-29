@props([
    'name',
    'value' => null,
    'type' => 'text',
    'label' => null,
    'hint' => null,
    'required' => false,
    'prefix' => null,
])

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $name }}" class="label">
            {{ $label }}
            @if ($required)
                <span class="text-rose-400">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-500">
                {{ $prefix }}
            </span>
        @endif

        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($value !== null) value="{{ $value }}" @endif
            @required($required)
            {{ $attributes->except('class')->merge([
                'class' => 'input ' . ($prefix ? 'pl-11' : ''),
            ]) }}
        />
    </div>

    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-xs font-medium text-rose-400">{{ $message }}</p>
    @enderror
</div>
