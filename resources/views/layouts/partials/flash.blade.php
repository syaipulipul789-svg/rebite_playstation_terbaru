@php
    $flashes = collect([
        'success' => ['emerald', 'check-circle-2', 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200'],
        'error' => ['rose', 'circle-alert', 'bg-rose-500/10 border-rose-500/30 text-rose-200'],
        'warning' => ['amber', 'triangle-alert', 'bg-amber-500/10 border-amber-500/30 text-amber-200'],
        'info' => ['sky', 'bell', 'bg-sky-500/10 border-sky-500/30 text-sky-200'],
    ])->filter(fn ($key) => session()->has($key));
@endphp

@if ($flashes->isNotEmpty())
    <div class="mb-5 space-y-2.5">
        @foreach ($flashes as $key => [$variant, $icon, $classes])
            <div
                x-data="{ show: true }"
                x-show="show"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $classes }}"
            >
                <x-icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0" />

                <p class="flex-1 font-medium">{{ session($key) }}</p>

                <button type="button" x-on:click="show = false" class="shrink-0 opacity-60 hover:opacity-100">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        @endforeach
    </div>
@endif
