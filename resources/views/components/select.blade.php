@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
    'options' => [],
    'placeholder' => '— Pilih —',
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

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @required($required)
        {{ $attributes->except('class')->merge(['class' => 'input appearance-none pr-9']) }}
    >
        @if ($placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(old($name, $attributes->get('value')) == $value)>
                {{ $text }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-xs font-medium text-rose-400">{{ $message }}</p>
    @enderror
</div>
