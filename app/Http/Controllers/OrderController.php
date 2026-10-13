<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CheckoutException;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    /**
     * The logged-in user's order history.
     */
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->withCount('items')
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * A single order's details (used both as the order-history detail
     * view and as the post-purchase confirmation page).
     */
    public function show(Request $request, Order $order): Response
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        return Inertia::render('Orders/Show', [
            'order' => $order->load('items.product:id,slug,is_active'),
            'courier' => \App\Services\CourierService::label($order->courier),
        ]);
    }

    /**
     * "Thank you" screen right after an order was placed or paid.
     */
    public function success(Request $request, Order $order): Response|RedirectResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        // Only meaningful for a confirmed order; anything else goes to the detail page.
        if (! $order->stock_deducted || $order->status === Order::STATUS_CANCELLED) {
            return redirect()->route('orders.show', $order);
        }

        return Inertia::render('Orders/Success', [
            'order' => $order->load('items.product:id,slug,is_active'),
        ]);
    }

    /**
     * Customers can cancel an order until it starts processing.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        if (! $order->can_cancel) {
            return back()->with('error', 'This order can no longer be cancelled. Please contact support.');
        }

        try {
            $this->orders->cancel($order);
        } catch (CheckoutException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Your order has been cancelled.');
    }

    /**
     * Printable invoice -- visible to the order's owner and to admins.
     */
    public function invoice(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return view('invoice', ['order' => $order->load('items', 'user')]);
    }
}
