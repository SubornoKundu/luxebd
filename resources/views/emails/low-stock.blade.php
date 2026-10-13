<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #111827; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 4px;">Low stock alert</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">These products have reached the low-stock level ({{ config('shop.low_stock_threshold') }} units). Time to restock.</p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
        @foreach ($products as $product)
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td>{{ $product->name }}</td>
                <td align="right"><strong>{{ $product->stock }} left</strong></td>
            </tr>
        @endforeach
    </table>

    <p style="font-size: 14px;"><a href="{{ url('/admin/inventory') }}">Open inventory in the admin panel</a></p>
    <p style="font-size: 12px; color: #9ca3af;">{{ config('app.name') }} automated alert.</p>
</body>
</html>
