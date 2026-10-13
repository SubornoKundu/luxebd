<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CourierService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Track my order": order number + the phone number used on the order.
 * Only status information is shown (never the address), and a wrong number and
 * an unknown order give the same answer.
 */
class TrackOrderController extends Controller
{
    public function show(Request $request): Response
    {
        $data = $request->validate([
            'order' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $searched = filled($data['order'] ?? null) || filled($data['phone'] ?? null);
        $result = null;

        if (filled($data['order'] ?? null) && filled($data['phone'] ?? null)) {
            $order = Order::query()
                ->where('order_number', strtoupper(trim($data['order'])))
                ->with('items:id,order_id,product_name,variant_label,quantity')
                ->first();

            if ($order && $this->samePhone($order->shipping_phone, $data['phone'])) {
                $result = [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'total_amount' => (float) $order->total_amount,
                    'placed_at' => $order->created_at?->toIso8601String(),
                    'steps' => $order->tracking_steps,
                    'courier' => $order->courier ? CourierService::label($order->courier) : null,
                    'tracking_code' => $order->tracking_code ?? null,
                    'items' => $order->items->map(fn ($i) => [
                        'name' => $i->product_name,
                        'variant' => $i->variant_label,
                        'quantity' => $i->quantity,
                    ])->values(),
                ];
            }
        }

        $this->seo('Track your order', 'See where your order is with your order number and phone number.');

        return Inertia::render('TrackOrder', [
            'query' => ['order' => $data['order'] ?? '', 'phone' => $data['phone'] ?? ''],
            'searched' => $searched && filled($data['order'] ?? null) && filled($data['phone'] ?? null),
            'result' => $result,
        ]);
    }

    /** Compare the last 10 digits so 017..., +88017... and 88017... all match. */
    protected function samePhone(?string $a, string $b): bool
    {
        $tail = fn (?string $v) => substr(preg_replace('/\D+/', '', (string) $v), -10);

        $x = $tail($a);

        return $x !== '' && strlen($x) >= 7 && $x === $tail($b);
    }
}
