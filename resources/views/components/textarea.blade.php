@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
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

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="3"
        @required($required)
        {{ $attributes->except('class')->merge(['class' => 'input resize-y']) }}
    >{{ old($name) }}</textarea>

    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-xs font-medium text-rose-400">{{ $message }}</p>
    @enderror
</div>
