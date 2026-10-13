{!! $headline !!}
Order {!! $order->order_number !!} - status: {!! ucfirst($order->status) !!}

@foreach ($order->items as $item)
- {!! $item->product_name !!}@if ($item->variant_label) ({!! $item->variant_label !!})@endif x {!! $item->quantity !!}: {!! config('shop.currency_symbol') !!}{!! number_format((float) $item->subtotal, 2) !!}
@endforeach

Shipping: {!! config('shop.currency_symbol') !!}{!! number_format((float) $order->shipping_fee, 2) !!}
Total: {!! config('shop.currency_symbol') !!}{!! number_format((float) $order->total_amount, 2) !!}
@if ($order->hasAdvance())
Advance paid by bKash: {!! config('shop.currency_symbol') !!}{!! number_format((float) $order->advance_amount, 2) !!}
Pay in cash on delivery: {!! config('shop.currency_symbol') !!}{!! number_format((float) $order->balance_due, 2) !!}
@endif

Payment: {!! ['cod' => 'Cash on delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad'][$order->payment_method] ?? 'Online' !!} ({!! $order->payment_status !!})
Ship to: {!! $order->shipping_name !!}, {!! $order->shipping_address !!}@if ($order->shipping_city), {!! $order->shipping_city !!}@endif

Thanks for shopping with {!! config('app.name') !!}.
