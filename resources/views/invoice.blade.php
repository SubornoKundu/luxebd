<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; max-width: 760px; margin: 32px auto; padding: 0 20px; font-size: 14px; }
        h1 { margin: 0; font-size: 26px; } .muted { color: #6b7280; }
        .row { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; } th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #6b7280; } .r { text-align: right; }
        .totals td { border: 0; } .grand td { font-weight: bold; font-size: 16px; border-top: 2px solid #111827; }
        button { margin-bottom: 20px; padding: 8px 18px; background: #16a34a; color: #fff; border: 0; border-radius: 999px; cursor: pointer; }
        @media print { button { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Print / Save as PDF</button>

    <div class="row">
        <div>
            @php
                $logoSrc = config('site.logo') ?: '/images/vireluxe/logo.png';
                $logoSrc = preg_match('#^https?://#i', $logoSrc) ? $logoSrc : url($logoSrc);
            @endphp
            <h1><img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" style="display:block;height:44px;width:auto"></h1>
            <div class="muted">Invoice</div>
        </div>
        <div class="r">
            <strong>{{ $order->order_number }}</strong><br>
            <span class="muted">Date: {{ $order->created_at->format('d M Y') }}</span><br>
            <span class="muted">Status: {{ ucfirst($order->status) }} &middot; Payment: {{ ucfirst($order->payment_status) }}</span>
        </div>
    </div>

    <div class="row">
        <div>
            <strong>Bill / Ship to</strong><br>
            {{ $order->shipping_name }}<br>
            {{ $order->shipping_address }}@if ($order->shipping_city), {{ $order->shipping_city }}@endif<br>
            {{ $order->shipping_phone }}<br>
            <span class="muted">{{ $order->user?->email }}</span>
        </div>
        <div class="r">
            <strong>Payment method</strong><br>
            {{ $order->isCod() ? 'Cash on delivery' : ($order->payment_method === 'bkash' ? 'bKash' : ($order->payment_method === 'nagad' ? 'Nagad' : ucfirst($order->payment_method))) }}
            @if ($order->payer_number)<br><span class="muted">From bKash: {{ $order->payer_number }}</span>@endif
            @if ($order->transaction_id)<br><span class="muted">Txn: {{ $order->transaction_id }}</span>@endif
            @if ($order->hasAdvance())<br><span class="muted">Advance paid: {{ config('shop.currency_symbol') }}{{ number_format((float) $order->advance_amount, 2) }} &middot; Balance in cash on delivery: {{ config('shop.currency_symbol') }}{{ number_format((float) $order->balance_due, 2) }}</span>@endif
        </div>
    </div>

    <table>
        <thead><tr><th>Item</th><th class="r">Price</th><th class="r">Qty</th><th class="r">Total</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}@if ($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif</td>
                    <td class="r">{{ config('shop.currency_symbol') }}{{ number_format((float) $item->price, 2) }}</td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r">{{ config('shop.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 12px; width: 280px; margin-left: auto;">
        <tr><td>Subtotal</td><td class="r">{{ config('shop.currency_symbol') }}{{ number_format((float) $order->subtotal, 2) }}</td></tr>
        @if ((float) $order->discount_amount > 0)
            <tr><td>Discount ({{ $order->coupon_code }})</td><td class="r">-{{ config('shop.currency_symbol') }}{{ number_format((float) $order->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td>Shipping</td><td class="r">{{ config('shop.currency_symbol') }}{{ number_format((float) $order->shipping_fee, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="r">{{ config('shop.currency_symbol') }}{{ number_format((float) $order->total_amount, 2) }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 40px;">Thank you for shopping with {{ config('app.name') }}.</p>
</body>
</html>
