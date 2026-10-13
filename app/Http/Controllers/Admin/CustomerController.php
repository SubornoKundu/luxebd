<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->input('q', ''));

        $customers = User::query()
            ->where('role', 'customer')
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('payment_status', Order::PAYMENT_PAID)], 'total_amount')
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => ['q' => $term],
        ]);
    }

    public function show(User $customer): Response
    {
        abort_if($customer->isAdmin(), 404);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'orders' => $customer->orders()->withCount('items')->latest('id')->take(20)->get(),
            'stats' => [
                'orders' => $customer->orders()->count(),
                'spent' => (float) $customer->orders()->where('payment_status', Order::PAYMENT_PAID)->sum('total_amount'),
            ],
        ]);
    }

    public function toggleBlock(User $customer): RedirectResponse
    {
        abort_if($customer->isAdmin(), 403, 'Admin accounts cannot be suspended.');

        $customer->forceFill(['is_blocked' => ! $customer->is_blocked])->save();

        return back()->with('success', $customer->is_blocked
            ? "{$customer->name} has been suspended."
            : "{$customer->name} can sign in again.");
    }
}
