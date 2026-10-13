<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Stock overview: totals, stock value, low / out-of-stock alerts and a
     * restock suggestion based on how fast each product sold in the last 30 days.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'view' => ['nullable', 'in:attention,all'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $threshold = (int) config('shop.low_stock_threshold');
        $view = $filters['view'] ?? 'attention';

        $products = Product::query()
            ->with(['category:id,name', 'variants'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($view === 'attention', fn ($q) => $q->where('stock', '<=', $threshold))
            ->orderBy('stock')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Units sold over the last 30 days (cancelled orders ignored) -- only for the 15
        // products on this page, so the page stays quick however many orders exist.
        $sold = $products->isEmpty() ? collect() : DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $products->pluck('id')->all())
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as product_id, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $products->getCollection()->transform(function (Product $p) use ($sold, $threshold) {
            $sold30 = (int) ($sold[$p->id] ?? 0);
            $perDay = $sold30 / 30;
            $daysLeft = $perDay > 0 ? (int) floor($p->stock / $perDay) : null;
            // Aim to hold 30 days of sales (and never less than twice the alert level).
            $target = max((int) ceil($perDay * 30), $threshold * 2);

            $p->setAttribute('sold_30d', $sold30);
            $p->setAttribute('days_left', $daysLeft);
            $p->setAttribute('suggested_restock', $p->stock < $target ? $target - $p->stock : 0);
            $p->setAttribute('level', $p->stock <= 0 ? 'out' : ($p->stock <= $threshold ? 'low' : 'ok'));
            $p->setAttribute('stock_value', round($p->stock * $p->final_price, 2));

            return $p;
        });

        // Plain table queries: Product always adds a `variants_count` sub-select, which
        // MySQL (only_full_group_by) refuses to mix with a SUM() in one query.
        $active = DB::table('products')->where('is_active', true);

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => ['view' => $view, 'q' => $filters['q'] ?? ''],
            'threshold' => $threshold,
            'summary' => [
                'products' => Product::count(),
                'units' => (int) Product::sum('stock'),
                'retail_value' => round((float) (clone $active)->sum(DB::raw('stock * COALESCE(discount_price, price)')), 2),
                'low' => (clone $active)->where('stock', '>', 0)->where('stock', '<=', $threshold)->count(),
                'out' => (clone $active)->where('stock', '<=', 0)->count(),
            ],
        ]);
    }

    /**
     * Quick restock: add units to a product without opening the full form.
     */
    public function restock(Request $request, Product $product)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100000']]);

        if ($product->variants()->exists()) {
            return back()->with('error', 'This product has size/colour options - restock them from the product edit page.');
        }

        $product->increment('stock', $data['quantity']);

        return back()->with('success', "Added {$data['quantity']} units to {$product->name}.");
    }
}
