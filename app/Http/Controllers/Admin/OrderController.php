<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutException;
use App\Services\CourierService;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,processing,shipped,completed,cancelled'],
            'payment' => ['nullable', 'in:pending,paid,failed,refunded'],
            'method' => ['nullable', 'in:cod,bkash,nagad'],
            // 1 = bKash/Nagad orders whose payment the admin has not checked yet.
            'check' => ['nullable', 'boolean'],
        ]);

        $orders = Order::query()
            ->with('user:id,name,email')
            ->withCount('items')
            ->when($filters['q'] ?? null, function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(fn ($q) => $q
                    ->where('order_number', 'like', $like)
                    ->orWhere('shipping_name', 'like', $like)
                    ->orWhere('shipping_phone', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['payment'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($filters['check'] ?? false, fn ($q) => $q
                ->where('payment_status', Order::PAYMENT_PENDING)
                ->whereIn('payment_method', [Order::METHOD_BKASH, Order::METHOD_NAGAD])
                ->where('status', '!=', Order::STATUS_CANCELLED))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'payment' => $filters['payment'] ?? '',
                'method' => $filters['method'] ?? '',
                'check' => ! empty($filters['check']) ? '1' : '',
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load('items.product:id,slug', 'user:id,name,email,phone'),
            'courierLabel' => CourierService::label($order->courier),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:processing,shipped,completed,cancelled'],
        ]);

        try {
            $this->orders->updateStatus($order, $data['status']);
        } catch (CheckoutException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order marked as {$data['status']}.");
    }

    /**
     * Courier name and tracking code, typed by the admin after handing the parcel over.
     * The customer sees them on the order page and on Track order. Leave both empty to clear.
     */
    public function courier(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'courier' => ['nullable', 'string', 'max:60'],
            'tracking_code' => ['nullable', 'string', 'max:100'],
        ]);

        $courier = trim((string) ($data['courier'] ?? '')) ?: null;
        $code = trim((string) ($data['tracking_code'] ?? '')) ?: null;

        $order->update([
            'courier' => $courier,
            'tracking_code' => $courier ? $code : null,
            'courier_booked_at' => $courier ? ($order->courier_booked_at ?? now()) : null,
        ]);

        return back()->with('success', $courier ? 'Courier details saved. The customer can see them now.' : 'Courier details removed.');
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', 'in:pending,paid,failed,refunded'],
        ]);

        if ($order->status === Order::STATUS_CANCELLED && $data['payment_status'] === Order::PAYMENT_PAID) {
            return back()->with('error', "A cancelled order can't be marked as paid.");
        }

        $order->update(['payment_status' => $data['payment_status']]);

        return back()->with('success', "Payment marked as {$data['payment_status']}.");
    }

    /**
     * Printable packing slips (one page per order). Select orders in the list
     * and pass their ids, e.g. /admin/orders/print?ids=3,5,8
     */
    public function print(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(100);

        abort_if($ids->isEmpty(), 404);

        $orders = Order::with(['items', 'user:id,name,email,phone'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        abort_if($orders->isEmpty(), 404);

        return view('packing-slip', [
            'orders' => $orders,
            'courierLabels' => [],
        ]);
    }
}
