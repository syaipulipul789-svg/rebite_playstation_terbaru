@php
    $user = auth()->user();

    $menu = $user->isOwner()
        ? [
            ['route' => 'owner.dashboard', 'label' => 'Dashboard Analitik', 'icon' => 'layout-dashboard', 'match' => 'owner.dashboard'],
            ['route' => 'owner.reports', 'label' => 'Laporan Keuangan', 'icon' => 'file-text', 'match' => 'owner.reports'],
            ['route' => 'owner.audit-log', 'label' => 'Audit Log Shift', 'icon' => 'history', 'match' => 'owner.audit-log'],

            ['header' => 'Data Master'],

            ['route' => 'owner.units.index', 'label' => 'Master Unit', 'icon' => 'monitor', 'match' => 'owner.units'],
            ['route' => 'owner.rate-packages.index', 'label' => 'Tarif Paket', 'icon' => 'tags', 'match' => 'owner.rate-packages'],
            ['route' => 'owner.products.index', 'label' => 'Produk F&B', 'icon' => 'shopping-bag', 'match' => 'owner.products'],
            ['route' => 'owner.users.index', 'label' => 'Akun Pengguna', 'icon' => 'users', 'match' => 'owner.users'],
        ]
        : [
            ['route' => 'units.index', 'label' => 'Grid Unit', 'icon' => 'layout-grid', 'match' => 'units.index'],
            ['route' => 'pos.index', 'label' => 'Kasir / POS', 'icon' => 'receipt', 'match' => 'pos.index'],
            ['route' => 'pos.orders', 'label' => 'Pesanan Barcode', 'icon' => 'barcode', 'match' => 'pos.orders'],
            ['route' => 'pos.bookings', 'label' => 'Daftar Booking', 'icon' => 'calendar-days', 'match' => 'pos.bookings'],
            ['route' => 'shift.end', 'label' => 'Rekonsiliasi Shift', 'icon' => 'wallet', 'match' => 'shift.end'],
        ];
@endphp

@foreach ($menu as $item)
    @if (isset($item['header']))
        <p class="px-3 pb-1 pt-4 text-[10px] font-bold uppercase tracking-widest text-slate-600">
            {{ $item['header'] }}
        </p>
    @else
        @php $active = request()->routeIs($item['match']); @endphp

        <a
            href="{{ route($item['route']) }}"
            @class([
                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                'bg-brand-500/15 text-white ring-1 ring-inset ring-brand-500/30' => $active,
                'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active,
            ])
        >
            <span @class([
                'grid h-8 w-8 shrink-0 place-items-center rounded-lg transition',
                'bg-brand-500/20 text-brand-300' => $active,
                'bg-white/5 text-slate-500 group-hover:text-slate-300' => ! $active,
            ])>
                <x-icon :name="$item['icon']" class="h-4 w-4" />
            </span>

            {{ $item['label'] }}
        </a>
    @endif
@endforeach

@if (! $user->isOwner())
    @php $activeShift = $user->activeShift(); @endphp

    <div class="mt-4 rounded-xl border border-white/5 bg-ink-850 p-3">
        <div class="flex items-center justify-between gap-2">
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Shift</span>

            @if ($activeShift)
                <x-badge variant="sky" dot>Berjalan</x-badge>
            @else
                <x-badge variant="amber" dot>Belum mulai</x-badge>
            @endif
        </div>

        @if ($activeShift)
            <p class="tabular mt-2 text-sm font-semibold text-slate-200">
                {{ $activeShift->start_time->format('H:i') }} WIB
            </p>
            <p class="tabular text-xs text-slate-500">
                Modal {{ \App\Support\Money::format($activeShift->starting_cash) }}
            </p>
        @else
            <a href="{{ route('shift.start') }}" class="btn-primary btn-sm mt-3 w-full">
                <x-icon name="play" class="h-3.5 w-3.5" />
                Mulai Shift
            </a>
        @endif
    </div>
@endif
