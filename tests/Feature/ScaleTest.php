<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\StorefrontCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Behaviour that matters when the shop has lots of visitors, orders and products. */
class ScaleTest extends TestCase
{
    use Concerns, RefreshDatabase;

    /** @return list<array<string, mixed>> */
    protected function orderRows(int $userId, int $count, string $status, string $payment, \DateTimeInterface $at, float $total): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id' => $userId, 'order_number' => 'BULK-'.uniqid('', true).'-'.$i, 'subtotal' => $total, 'shipping_fee' => 0,
                'discount_amount' => 0, 'total_amount' => $total, 'status' => $status, 'payment_status' => $payment,
                'payment_method' => 'cod', 'shipping_name' => 'a', 'shipping_phone' => '01711111111', 'shipping_address' => 'x',
                'shipping_city' => 'Dhaka', 'stock_deducted' => true, 'created_at' => $at, 'updated_at' => $at,
            ];
        }

        return $rows;
    }

    public function test_performance_indexes_exist(): void
    {
        foreach ([
            'products' => ['products_active_created_idx', 'products_active_stock_idx', 'products_category_active_idx'],
            'orders' => ['orders_status_idx', 'orders_payment_created_idx', 'orders_created_idx', 'orders_user_coupon_idx'],
            'reviews' => ['reviews_product_approved_idx'],
            'product_questions' => ['product_questions_answered_idx'],
        ] as $table => $names) {
            foreach ($names as $name) {
                $this->assertTrue(Schema::hasIndex($table, $name), "missing index {$name}");
            }
        }
    }

    public function test_dashboard_figures_are_exact_with_thousands_of_orders_and_use_few_queries(): void
    {
        $buyer = $this->makeUser();
        // "Today" rows are placed just after midnight so the test also passes when it runs right after 00:00
        // (an hour ago would still be yesterday).
        $now = now();
        $today = $now->copy()->startOfDay()->addMinute();

        DB::table('orders')->insert($this->orderRows($buyer->id, 1200, 'completed', 'paid', $today, 100));      // today, paid
        DB::table('orders')->insert($this->orderRows($buyer->id, 300, 'pending', 'pending', $today, 50));      // today, unpaid
        DB::table('orders')->insert($this->orderRows($buyer->id, 40, 'cancelled', 'refunded', $today, 70));    // today, cancelled
        DB::table('orders')->insert($this->orderRows($buyer->id, 500, 'completed', 'paid', $now->copy()->subDays(3)->setTime(12, 0), 200));
        DB::table('orders')->insert($this->orderRows($buyer->id, 90, 'completed', 'paid', $now->copy()->subDays(100), 10));         // outside the 90-day chart

        $admin = $this->makeAdmin();
        DB::enableQueryLog();
        $res = $this->actingAs($admin)->get('/admin')->assertOk();
        $queries = count(DB::getQueryLog());

        $res->assertInertia(fn (Assert $p) => $p
            ->where('stats.orders', 2130)
            ->where('stats.pending_orders', 300)
            ->where('stats.revenue', 1200 * 100 + 500 * 200 + 90 * 10)
            ->where('stats.revenue_today', 120000)
            ->where('daily.today.orders', 1500)                    // cancelled ones are not sales
            ->where('daily.today.sales', 1200 * 100 + 300 * 50)
            ->where('daily.today.paid', 120000)
            ->where('daily.today.cancelled', 40)
            ->where('series.89.orders', 1500)
            ->where('series.86.orders', 500)
            ->where('series.86.sales', 100000)
            ->where('chart.13.total', 120000)
            ->where('chart.10.total', 100000)
            ->where('statusCounts.pending', 300)
            ->has('recentOrders', 8));

        $this->assertLessThan(45, $queries, "dashboard ran {$queries} queries");
    }

    public function test_dashboard_query_count_does_not_grow_with_the_number_of_orders(): void
    {
        $buyer = $this->makeUser();
        $admin = $this->makeAdmin();

        DB::table('orders')->insert($this->orderRows($buyer->id, 5, 'completed', 'paid', now(), 10));
        $this->actingAs($admin)->get('/admin')->assertOk(); // warm-up (settings, session)
        DB::enableQueryLog();
        $this->get('/admin')->assertOk();
        $few = count(DB::getQueryLog());

        DB::table('orders')->insert($this->orderRows($buyer->id, 800, 'completed', 'paid', now(), 10));
        DB::flushQueryLog();
        $this->get('/admin')->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    public function test_inventory_page_counts_sales_only_for_the_products_it_shows(): void
    {
        $buyer = $this->makeUser();
        $product = $this->makeProduct(['stock' => 4]);
        $order = Order::create([
            'user_id' => $buyer->id, 'order_number' => 'T-INV', 'subtotal' => 1, 'total_amount' => 1, 'status' => 'completed',
            'shipping_name' => 'a', 'shipping_phone' => '1', 'shipping_address' => 'x', 'shipping_city' => 'y',
        ]);
        \App\Models\OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'x', 'price' => 1, 'quantity' => 30, 'subtotal' => 30]);

        $this->actingAs($this->makeAdmin())->get('/admin/inventory')->assertInertia(fn (Assert $p) => $p
            ->where('products.data.0.sold_30d', 30)
            ->where('products.data.0.days_left', 4));
    }

    /** The database cache really serialises values (like the live site); the default test cache does not. */
    protected function useDatabaseCache(): void
    {
        config(['shop.storefront_cache_seconds' => 60, 'shop.popular_cache_seconds' => 60, 'shop.stats_cache_seconds' => 60, 'cache.default' => 'database']);
        app('cache')->forgetDriver('database');
        Cache::flush();
    }

    public function test_cached_home_page_still_shows_everything_on_every_visit_and_for_logged_in_users(): void
    {
        $this->useDatabaseCache();
        $this->makeProduct(['name' => 'Cached Phone', 'is_featured' => true]);
        $user = $this->makeUser();

        $check = fn (Assert $p) => $p->where('featuredProducts.0.name', 'Cached Phone')->has('latestProducts', 1)->has('brands', 1)->has('categories', 1);

        $this->get('/')->assertInertia($check);          // first visit: built from the database
        $this->get('/')->assertInertia($check);          // second visit: read back from the cache
        $this->actingAs($user)->get('/')->assertInertia($check); // logged in
        $this->post('/logout');
        $this->get('/')->assertInertia($check);          // and after logging out
    }

    public function test_cached_admin_dashboard_numbers_survive_the_cache(): void
    {
        $this->useDatabaseCache();
        $buyer = $this->makeUser();
        $admin = $this->makeAdmin();
        DB::table('orders')->insert($this->orderRows($buyer->id, 3, 'completed', 'paid', now(), 100));

        foreach ([1, 2] as $visit) {
            $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('stats.revenue', 300)->where('stats.orders', 3)->has('series', 90)->has('chart', 14)
                ->where('statusCounts.completed', 3));
        }
    }

    public function test_home_page_is_cached_and_refreshes_when_the_catalogue_changes(): void
    {
        $this->useDatabaseCache();
        $product = $this->makeProduct(['name' => 'First Phone', 'is_featured' => true]);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('featuredProducts.0.name', 'First Phone'));

        DB::enableQueryLog();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('featuredProducts.0.name', 'First Phone'));
        $productQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "products"') || str_contains($q['query'], 'from `products`'))->count();
        $this->assertSame(0, $productQueries, 'second visit should come from the cache');

        // an admin edit shows up straight away
        $product->update(['name' => 'Renamed Phone']);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('featuredProducts.0.name', 'Renamed Phone'));

        // and so does hiding it
        $product->update(['is_active' => false]);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('featuredProducts', 0));
    }

    public function test_popular_ranking_matches_between_home_and_products_with_cache_on(): void
    {
        config(['shop.storefront_cache_seconds' => 60, 'shop.popular_cache_seconds' => 60]);
        Cache::flush();
        $user = $this->makeUser();
        $a = $this->makeProduct(['name' => 'A seller']);
        $b = $this->makeProduct(['name' => 'B seller']);
        $this->makeProduct(['name' => 'C never sold']);

        foreach ([[$a, 2], [$b, 9]] as [$p, $qty]) {
            $o = Order::create(['user_id' => $user->id, 'order_number' => 'T-'.$p->id, 'subtotal' => 1, 'total_amount' => 1, 'status' => 'completed',
                'shipping_name' => 'a', 'shipping_phone' => '1', 'shipping_address' => 'x', 'shipping_city' => 'y']);
            \App\Models\OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'price' => 1, 'quantity' => $qty, 'subtotal' => $qty]);
        }

        $this->assertSame([$b->id, $a->id], StorefrontCache::popularProductIds());
        $this->get('/products?sort=popular')->assertInertia(fn (Assert $p) => $p
            ->where('products.data.0.name', 'B seller')->where('products.data.1.name', 'A seller')->where('products.data.2.name', 'C never sold'));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('popularProducts', 2)->where('popularProducts.0.name', 'B seller'));
    }

    public function test_listing_queries_do_not_grow_with_the_number_of_products(): void
    {
        $this->makeProduct();
        $this->get('/products')->assertOk(); // warm-up
        DB::enableQueryLog();
        $this->get('/products')->assertOk();
        $one = count(DB::getQueryLog());

        foreach (range(1, 11) as $i) {
            $this->makeProduct();
        }
        DB::flushQueryLog();
        $this->get('/products')->assertOk()->assertInertia(fn (Assert $p) => $p->has('products.data', 12));

        $this->assertSame($one, count(DB::getQueryLog()), 'N+1 on the product list');
    }

    public function test_order_emails_go_through_the_mail_queue(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());

        app(OrderService::class)->place($user, $this->shipping(), 'cod');

        Mail::assertQueued(OrderStatusMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_a_broken_cache_never_breaks_the_home_page(): void
    {
        config(['shop.storefront_cache_seconds' => 60]);
        $this->makeProduct(['is_featured' => true]);
        $this->assertSame('ok', \App\Services\StorefrontCache::remember('x', fn () => 'ok', 60));
        config(['cache.default' => 'broken']);
        config(['cache.stores.broken' => ['driver' => 'file', 'path' => '/proc/nonexistent/cache']]);
        app('cache')->forgetDriver('broken');
        $this->assertSame('ok', \App\Services\StorefrontCache::remember('y', fn () => 'ok', 60));
        \App\Services\StorefrontCache::flush(); // must not throw
    }
}
