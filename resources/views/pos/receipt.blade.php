<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Nota #{{ str_pad((string) $session->id, 5, '0', STR_PAD_LEFT) }} — Rebite Playstation</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #e2e8f0;
            color: #0f172a;
            line-height: 1.5;
        }

        .sheet {
            max-width: 340px;
            margin: 0 auto;
            background: #fff;
            padding: 24px 20px 20px;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgb(15 23 42 / 0.18);
        }

        .brand { text-align: center; padding-bottom: 12px; }
        .brand h1 { margin: 0; font-size: 17px; font-weight: 800; letter-spacing: -0.02em; }
        .brand p { margin: 2px 0 0; font-size: 10px; color: #64748b; }

        .meta { border-top: 1px dashed #cbd5e1; padding-top: 12px; }
        .row { display: flex; justify-content: space-between; gap: 12px; font-size: 11.5px; }
        .row + .row { margin-top: 5px; }
        .row dt { color: #64748b; }
        .row dd { margin: 0; font-weight: 600; text-align: right; }

        .section { border-top: 1px dashed #cbd5e1; margin-top: 12px; padding-top: 12px; }
        .section-title {
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
            margin-bottom: 7px;
        }

        .item { display: flex; justify-content: space-between; gap: 12px; font-size: 11.5px; }
        .item + .item { margin-top: 5px; }
        .item .qty { color: #94a3b8; }
        .item .amount { font-variant-numeric: tabular-nums; font-weight: 600; }

        .total {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-top: 2px solid #0f172a;
            margin-top: 12px;
            padding-top: 10px;
        }
        .total span:first-child { font-size: 12px; font-weight: 800; }
        .total span:last-child { font-size: 19px; font-weight: 800; font-variant-numeric: tabular-nums; }

        .paid {
            margin-top: 12px;
            border-radius: 8px;
            background: #eef2ff;
            padding: 9px 11px;
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
        }
        .paid span:first-child { color: #4338ca; }
        .paid span:last-child { font-weight: 800; color: #3730a3; }

        .running {
            margin-top: 12px;
            border-radius: 8px;
            background: #fef3c7;
            padding: 9px 11px;
            font-size: 11px;
            color: #92400e;
        }

        .footer {
            margin-top: 16px;
            text-align: center;
            font-size: 9.5px;
            color: #94a3b8;
        }

        .actions {
            max-width: 340px;
            margin: 16px auto 0;
            display: flex;
            gap: 8px;
        }

        .actions button {
            flex: 1;
            border: 0;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 12px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            background: #4f46e5;
            color: #fff;
            transition: background 0.15s ease;
        }

        .actions button.ghost { background: #e2e8f0; color: #334155; }
        .actions button:hover { filter: brightness(0.95); }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; border-radius: 0; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body class="h-full">
    <div class="sheet">
        <div class="brand">
            <h1>REBITE PLAYSTATION</h1>
            <p>Nota penyewaan konsol · {{ $session->start_time->format('d F Y H:i') }}</p>
        </div>

        <dl class="meta">
            <div class="row">
                <dt>No. Nota</dt>
                <dd>#{{ str_pad((string) $session->id, 5, '0', STR_PAD_LEFT) }}</dd>
            </div>

            <div class="row">
                <dt>Unit</dt>
                <dd>{{ $session->unit->name }} ({{ $session->unit->code }})</dd>
            </div>

            <div class="row">
                <dt>Paket</dt>
                <dd>{{ $session->is_free_play ? 'Open Play' : $session->package_name }}</dd>
            </div>

            <div class="row">
                <dt>Mulai</dt>
                <dd>{{ $session->start_time->format('H:i') }}</dd>
            </div>

            <div class="row">
                <dt>Selesai</dt>
                <dd>{{ ($session->end_time ?? now())->format('H:i') }}</dd>
            </div>

            <div class="row">
                <dt>Durasi</dt>
                <dd>{{ $session->duration_minutes }} menit</dd>
            </div>

            <div class="row">
                <dt>Kasir</dt>
                <dd>{{ $session->user->name }}</dd>
            </div>
        </dl>

        <div class="section">
            <p class="section-title">Pesanan F&amp;B</p>

            @forelse ($session->items as $item)
                <div class="item">
                    <span>
                        {{ $item->qty }}× {{ $item->product->name }}
                        <span class="qty">({{ \App\Support\Money::format($item->price, false) }})</span>
                    </span>
                    <span class="amount">{{ \App\Support\Money::format($item->subtotal, false) }}</span>
                </div>
            @empty
                <p class="item qty">Tidak ada pesanan tambahan.</p>
            @endforelse
        </div>

        @if ($session->billableOrders->isNotEmpty())
            <div class="section">
                <p class="section-title">Pesanan QR Meja</p>

                @foreach ($session->billableOrders as $order)
                    <p class="item qty" style="margin-bottom:4px">
                        {{ $order->code }} · {{ $order->customer_name }}
                    </p>

                    @foreach ($order->items as $item)
                        <div class="item">
                            <span>
                                {{ $item->qty }}× {{ $item->product?->name }}
                                @if ($item->notes)
                                    <span class="qty">({{ $item->notes }})</span>
                                @endif
                            </span>
                            <span class="amount">{{ \App\Support\Money::format($item->subtotal, false) }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif

        <div class="section">
            <div class="item">
                <span>Biaya sewa</span>
                <span class="amount">{{ \App\Support\Money::format($session->rental_fee, false) }}</span>
            </div>

            <div class="item" style="margin-top:5px">
                <span>Subtotal F&amp;B</span>
                <span class="amount">{{ \App\Support\Money::format($session->itemsTotal(), false) }}</span>
            </div>

            @if ($session->ordersTotal() > 0)
                <div class="item" style="margin-top:5px">
                    <span>Subtotal QR meja</span>
                    <span class="amount">{{ \App\Support\Money::format($session->ordersTotal(), false) }}</span>
                </div>
            @endif
        </div>

        <div class="total">
            <span>TOTAL</span>
            <span>{{ \App\Support\Money::format($session->grandTotal()) }}</span>
        </div>

        @if ($session->isRunning())
            <div class="paid">
                <span>Belum dibayar</span>
                <span>{{ $session->status->label() }}</span>
            </div>

            <div class="running">
                Nota ini untuk tagihan berjalan. Selesaikan pembayaran dari grid unit untuk mengunci harga.
            </div>
        @else
            <div class="paid">
                <span>Sudah dibayar via</span>
                <span>{{ $session->payment_method?->label() }}</span>
            </div>
        @endif

        <p class="footer">
            Terima kasih sudah bermain di Rebite Playstation.<br>
            Simpan nota ini sebagai bukti transaksi.
        </p>
    </div>

    <div class="actions no-print">
        <button type="button" class="ghost" onclick="window.close()">Tutup</button>
        <button type="button" onclick="window.print()">Cetak Nota</button>
    </div>
</body>
</html>
