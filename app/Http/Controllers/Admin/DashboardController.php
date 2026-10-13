<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every figure here is calculated by the database (SUM / COUNT / GROUP BY over a date
 * range), never by loading orders into PHP, so the page stays fast and light on memory
 * even with hundreds of thousands of orders. The heavy, slow-moving totals are also
 * reused for a short time (config shop.stats_cache_seconds).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('status');
        $filter = in_array($filter, ['pending', 'processing', 'shipped'], true) ? $filter : 'all';

        $threshold = (int) config('shop.low_stock_threshold');

        $totals = $this->cached('totals', fn () => [
            'revenue' => (float) DB::table('orders')->where('payment_status', Order::PAYMENT_PAID)->sum('total_amount'),
            'orders' => DB::table('orders')->count(),
            'customers' => DB::table('users')->where('role', 'customer')->count(),
        ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'revenue' => $totals['revenue'],
                'revenue_today' => (float) DB::table('orders')
                    ->where('payment_status', Order::PAYMENT_PAID)
                    ->where('created_at', '>=', today())
                    ->sum('total_amount'),
                'orders' => $totals['orders'],
                'pending_orders' => Order::where('status', Order::STATUS_PENDING)->count(),
                'customers' => $totals['customers'],
                'products' => Product::count(),
                'low_stock' => Product::where('is_active', true)->where('stock', '<=', $threshold)->count(),
            ],
            'daily' => $this->dailySummary(),
            'series' => $this->cached('series', fn () => $this->series(90)),
            'categorySales' => $this->cached('category-sales', fn () => $this->categorySales()),
            'statusCounts' => $this->cached('status-counts', fn () => DB::table('orders')
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')),
            'chart' => $this->cached('chart', fn () => $this->paidChart(14)),
            'topProducts' => $this->cached('top-products', fn () => DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', '!=', Order::STATUS_CANCELLED)
                ->selectRaw('order_items.product_name, sum(order_items.quantity) as qty, sum(order_items.subtotal) as revenue')
                ->groupBy('order_items.product_name')
                ->orderByDesc('qty')
                ->limit(5)
                ->get()),
            'lowStock' => Product::where('is_active', true)->where('stock', '<=', $threshold)->orderBy('stock')->take(6)->get(['id', 'name', 'stock', 'image']),
            'recentStatus' => $filter,
            'recentOrders' => Order::with('user:id,name')
                ->when($filter !== 'all', fn ($q) => $q->where('status', $filter))
                ->latest('id')
                ->take(8)
                ->get(),
        ]);
    }

    /**
     * Reuse a slow figure for a few seconds (shop.stats_cache_seconds; 0 = always fresh).
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function cached(string $key, \Closure $callback): mixed
    {
        $ttl = (int) config('shop.stats_cache_seconds');

        if ($ttl <= 0) {
            return $callback();
        }

        try {
            // plain arrays only: the cache cannot restore objects (see StorefrontCache::plain)
            return Cache::remember('admin.dashboard.'.$key, $ttl, fn () => \App\Services\StorefrontCache::plain($callback()));
        } catch (\Throwable $e) {
            report($e);

            return $callback();
        }
    }

    /**
     * Today's numbers next to yesterday's. "Sales" counts every order placed
     * that day except cancelled ones; "paid" is the part already collected.
     *
     * @return array<string, mixed>
     */
    protected function dailySummary(): array
    {
        $figures = function (CarbonInterface $date) {
            $start = $date->copy()->startOfDay();
            $end = $start->copy()->addDay();

            $day = fn () => DB::table('orders')->where('orders.created_at', '>=', $start)->where('orders.created_at', '<', $end);

            $row = $day()
                ->where('orders.status', '!=', Order::STATUS_CANCELLED)
                ->selectRaw(
                    'COUNT(*) as n, COALESCE(SUM(orders.total_amount), 0) as sales, '
                    .'COALESCE(SUM(CASE WHEN orders.payment_status = ? THEN orders.total_amount ELSE 0 END), 0) as paid',
                    [Order::PAYMENT_PAID],
                )
                ->first();

            $units = (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.created_at', '>=', $start)
                ->where('orders.created_at', '<', $end)
                ->where('orders.status', '!=', Order::STATUS_CANCELLED)
                ->sum('order_items.quantity');

            $count = (int) $row->n;
            $sales = round((float) $row->sales, 2);

            return [
                'orders' => $count,
                'sales' => $sales,
                'paid' => round((float) $row->paid, 2),
                'units' => $units,
                'average' => $count ? round($sales / $count, 2) : 0.0,
                'cancelled' => $day()->where('orders.status', Order::STATUS_CANCELLED)->count(),
            ];
        };

        $today = $figures(today());
        $yesterday = $figures(today()->subDay());

        $change = fn ($now, $before) => $before > 0 ? round((($now - $before) / $before) * 100, 1) : null;

        return [
            'date' => today()->format('D, d M Y'),
            'today' => $today,
            'yesterday' => $yesterday,
            'change' => [
                'sales' => $change($today['sales'], $yesterday['sales']),
                'orders' => $change($today['orders'], $yesterday['orders']),
            ],
        ];
    }

    /**
     * Paid revenue per day for the last $days days (the small bar chart).
     *
     * @return list<array{label: string, total: float}>
     */
    protected function paidChart(int $days): array
    {
        $byDay = $this->perDay(now()->subDays($days - 1)->startOfDay(), fn ($q) => $q->where('payment_status', Order::PAYMENT_PAID));

        return collect(range($days - 1, 0))->map(function ($i) use ($byDay) {
            $day = now()->subDays($i)->startOfDay();

            return [
                'label' => $day->format('d M'),
                'total' => round((float) ($byDay[$day->format('Y-m-d')]['sales'] ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * One row per day for the interactive chart (sales + order count).
     *
     * @return list<array{date: string, label: string, sales: float, orders: int}>
     */
    protected function series(int $days): array
    {
        $byDay = $this->perDay(
            now()->subDays($days - 1)->startOfDay(),
            fn ($q) => $q->where('status', '!=', Order::STATUS_CANCELLED),
        );

        return collect(range($days - 1, 0))->map(function ($i) use ($byDay) {
            $day = now()->subDays($i)->startOfDay();
            $row = $byDay[$day->format('Y-m-d')] ?? null;

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('d M'),
                'sales' => round((float) ($row['sales'] ?? 0), 2),
                'orders' => (int) ($row['orders'] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Sales total and order count per calendar day since $from, grouped by the database.
     *
     * @return array<string, array{sales: float, orders: int}>
     */
    protected function perDay(CarbonInterface $from, \Closure $constrain): array
    {
        $query = DB::table('orders')->where('created_at', '>=', $from);
        $constrain($query);

        return $query
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(total_amount), 0) as sales, COUNT(*) as orders')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->day => ['sales' => (float) $r->sales, 'orders' => (int) $r->orders]])
            ->all();
    }

    /**
     * Revenue per top-level category (sub-categories roll up into their parent).
     *
     * @return list<array{name: string, revenue: float, units: int}>
     */
    protected function categorySales(): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('categories as parents', 'parents.id', '=', 'categories.parent_id')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->select([
                DB::raw('COALESCE(parents.name, categories.name) as name'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
                DB::raw('SUM(order_items.quantity) as units'),
            ])
            ->groupBy(DB::raw('COALESCE(parents.name, categories.name)'))
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'revenue' => round((float) $r->revenue, 2),
            'units' => (int) $r->units,
        ])->all();
    }
}
