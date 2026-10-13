<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #111827; max-width: 560px; margin: 0 auto; padding: 24px;">
    @php
        $logoSrc = config('site.logo') ?: '/images/vireluxe/logo.png';
        $logoSrc = preg_match('#^https?://#i', $logoSrc) ? $logoSrc : url($logoSrc);
    @endphp
    <img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" height="40" style="display: block; height: 40px; width: auto; border: 0; margin: 0 0 20px;">
    <h2 style="margin: 0 0 4px;">{{ $headline }}</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Order {{ $order->order_number }} &middot; Status: <strong>{{ ucfirst($order->status) }}</strong></p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
        @foreach ($order->items as $item)
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td>{{ $item->product_name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif &times; {{ $item->quantity }}</td>
                <td align="right">{{ config('shop.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
        @endforeach
        @if ((float) $order->discount_amount > 0)
            <tr><td>Discount ({{ $order->coupon_code }})</td><td align="right">-{{ config('shop.currency_symbol') }}{{ number_format((float) $order->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td>Shipping</td><td align="right">{{ config('shop.currency_symbol') }}{{ number_format((float) $order->shipping_fee, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td align="right"><strong>{{ config('shop.currency_symbol') }}{{ number_format((float) $order->total_amount, 2) }}</strong></td></tr>
        @if ($order->hasAdvance())
            <tr><td>Advance paid by bKash</td><td align="right">{{ config('shop.currency_symbol') }}{{ number_format((float) $order->advance_amount, 2) }}</td></tr>
            <tr><td><strong>Pay in cash on delivery</strong></td><td align="right"><strong>{{ config('shop.currency_symbol') }}{{ number_format((float) $order->balance_due, 2) }}</strong></td></tr>
        @endif
    </table>

    <p style="font-size: 14px; color: #374151;">
        Payment: {{ ['cod' => 'Cash on delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad'][$order->payment_method] ?? 'Online' }} ({{ $order->payment_status }})<br>
        Ship to: {{ $order->shipping_name }}, {{ $order->shipping_address }}@if ($order->shipping_city), {{ $order->shipping_city }}@endif
    </p>
    <p style="font-size: 12px; color: #9ca3af;">Thanks for shopping with {{ config('app.name') }}.</p>
</body>
</html>
