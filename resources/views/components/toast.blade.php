<div
    x-data="{}"
    class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4"
>
    <template x-for="item in $store.toast.items" :key="item.id">
        <div
            class="animate-toast-in pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-2xl backdrop-blur"
            :class="{
                'border-emerald-500/30 bg-emerald-950/90 text-emerald-100': item.type === 'success',
                'border-rose-500/30 bg-rose-950/90 text-rose-100': item.type === 'error',
                'border-amber-500/30 bg-amber-950/90 text-amber-100': item.type === 'warning',
                'border-sky-500/30 bg-sky-950/90 text-sky-100': item.type === 'info',
            }"
        >
            <i
                :data-lucide="{
                    success: 'check-circle-2',
                    error: 'circle-alert',
                    warning: 'triangle-alert',
                    info: 'bell',
                }[item.type]"
                class="mt-0.5 h-4 w-4 shrink-0"
            ></i>

            <p x-text="item.message" class="flex-1 font-medium"></p>

            <button type="button" x-on:click="$store.toast.dismiss(item.id)" class="shrink-0 opacity-60 hover:opacity-100">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
    </template>
</div>
