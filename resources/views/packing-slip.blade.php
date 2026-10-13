<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing slips ({{ $orders->count() }})</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 16px; font-size: 14px; background: #f3f4f6; }
        .bar { max-width: 760px; margin: 0 auto 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
        button { padding: 10px 18px; background: #16a34a; color: #fff; border: 0; border-radius: 999px; cursor: pointer; font-size: 14px; }
        .slip { max-width: 760px; margin: 0 auto 20px; background: #fff; padding: 24px; border: 1px solid #d1d5db; border-radius: 8px; page-break-after: always; }
        .slip:last-child { page-break-after: auto; }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 22px; } h2 { margin: 0 0 4px; font-size: 13px; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; }
        .muted { color: #6b7280; } .r { text-align: right; } .mono { font-family: "Courier New", monospace; }
        .grid { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .box { flex: 1 1 260px; border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; }
        .ship { font-size: 17px; line-height: 1.4; }
        .cod { background: #111827; color: #fff; border-radius: 6px; padding: 10px 14px; font-size: 18px; font-weight: bold; margin-bottom: 16px; }
        .paid { background: #ecfdf5; color: #065f46; border-radius: 6px; padding: 10px 14px; font-weight: bold; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; } th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .tick { width: 18px; height: 18px; border: 2px solid #111827; display: inline-block; border-radius: 3px; }
        .note { border: 1px dashed #9ca3af; border-radius: 6px; padding: 10px; margin-top: 14px; }
        .foot { margin-top: 18px; font-size: 12px; color: #6b7280; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        @media (max-width: 520px) { .slip { padding: 14px; } .r { text-align: left; } }
        @media print { body { background: #fff; padding: 0; } .bar { display: none; } .slip { border: 0; margin: 0; border-radius: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <span class="muted">{{ $orders->count() }} packing slip{{ $orders->count() === 1 ? '' : 's' }} &middot; one page each</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    @foreach ($orders as $order)
        <section class="slip">
            <div class="head">
                <div>
                    @php
                        $logoSrc = config('site.logo') ?: '/images/vireluxe/logo.png';
                        $logoSrc = preg_match('#^https?://#i', $logoSrc) ? $logoSrc : url($logoSrc);
                    @endphp
                    <h1><img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" style="display:block;height:40px;width:auto"></h1>
                    <div class="muted">Packing slip &amp; shipping label</div>
                </div>
                <div class="r">
                    <strong class="mono" style="font-size:18px">{{ $order->order_number }}</strong><br>
                    <span class="muted">{{ $order->created_at->format('d M Y, h:i A') }}</span><br>
                    <span class="muted">Items: {{ $order->items->sum('quantity') }}</span>
                </div>
            </div>

            @if ($order->hasAdvance())
                <div class="cod">COLLECT {{ config('shop.currency_symbol') }}{{ number_format((float) $order->balance_due, 2) }} cash on delivery
                    <span style="font-weight:normal;font-size:13px"> (total {{ config('shop.currency_symbol') }}{{ number_format((float) $order->total_amount, 2) }} &minus; {{ config('shop.currency_symbol') }}{{ number_format((float) $order->advance_amount, 2) }} bKash advance{{ $order->isPaid() ? ' received' : ', not confirmed yet' }})</span>
                </div>
            @elseif ($order->isPaid())
                <div class="paid">PAID &mdash; no cash to collect</div>
            @else
                <div class="cod">COLLECT {{ config('shop.currency_symbol') }}{{ number_format((float) $order->total_amount, 2) }} on delivery
                    @if (! $order->isCod())<span style="font-weight:normal;font-size:13px"> (payment via {{ ucfirst($order->payment_method) }} not confirmed yet)</span>@endif
                </div>
            @endif

            <div class="grid">
                <div class="box">
                    <h2>Ship to</h2>
                    <div class="ship">
                        <strong>{{ $order->shipping_name }}</strong><br>
                        {{ $order->shipping_address }}@if ($order->shipping_city), {{ $order->shipping_city }}@endif<br>
                        <strong>{{ $order->shipping_phone }}</strong>
                    </div>
                </div>
                <div class="box">
                    <h2>Courier</h2>
                    @if ($order->courier)
                        <strong>{{ $courierLabels[$order->courier] ?? ucfirst($order->courier) }}</strong><br>
                        <span class="mono">{{ $order->tracking_code }}</span>
                    @else
                        <span class="muted">Not booked yet</span>
                    @endif
                    <div class="muted" style="margin-top:8px">Payment: {{ ['cod' => 'Cash on delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad'][$order->payment_method] ?? 'Online' }} ({{ $order->payment_status }})</div>
                </div>
            </div>

            <table>
                <thead><tr><th style="width:28px"></th><th>Item</th><th class="r" style="width:60px">Qty</th></tr></thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td><span class="tick"></span></td>
                            <td>{{ $item->product_name }}@if ($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif</td>
                            <td class="r"><strong>{{ $item->quantity }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($order->notes)
                <div class="note"><strong>Customer note:</strong> {{ $order->notes }}</div>
            @endif

            <div class="foot">
                <span>Packed by: ____________ &nbsp; Checked by: ____________</span>
                <span>Thank you for shopping with {{ config('app.name') }}.</span>
            </div>
        </section>
    @endforeach
</body>
</html>
