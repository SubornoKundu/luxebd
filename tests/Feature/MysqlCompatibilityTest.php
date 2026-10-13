<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The tests run on SQLite, which is far more forgiving than MySQL. This guards
 * against the classic MySQL-only crash (ONLY_FULL_GROUP_BY): an aggregate such as
 * SUM() mixed with plain columns / the automatic `variants_count` sub-select.
 */
class MysqlCompatibilityTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_no_page_mixes_an_aggregate_with_plain_columns(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeUser();
        $product = $this->makeProduct();
        $this->addToCart($customer, $product, 1);
        $this->actingAs($customer)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();
        Review::create(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 4]);

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $this->get('/');
        $this->get('/products');
        $this->get('/products?sort=price_asc&q=ph');
        $this->get("/products/{$product->slug}");
        $this->get('/search?q=ph');

        $this->actingAs($customer);
        foreach (['/cart', '/checkout', '/orders', "/orders/{$order->id}", '/wishlist', '/addresses'] as $url) {
            $this->get($url);
        }

        $this->actingAs($admin);
        foreach (['/admin', '/admin/products', '/admin/inventory', '/admin/inventory?view=all', '/admin/orders', "/admin/orders/{$order->id}",
            '/admin/customers', '/admin/reviews', '/admin/questions', '/admin/coupons', '/admin/categories', '/admin/brands',
            '/admin/sliders', '/admin/settings', '/admin/admins'] as $url) {
            $this->get($url);
        }

        $this->assertNotEmpty($queries);

        $bad = array_values(array_filter($queries, function (string $sql) {
            // Ignore correlated sub-selects such as the automatic `variants_count`.
            $top = $sql;
            do {
                $before = $top;
                $top = preg_replace('/\(select(?:[^()]++|(?<p>\((?:[^()]++|(?&p))*\)))*\)/i', '', $top);
            } while ($top !== $before);

            $aggregate = preg_match('/^select\s.*\b(sum|avg|min|max|count)\(/is', $top) === 1;
            $grouped = stripos($top, ' group by ') !== false;
            // Plain columns next to the aggregate: `select "products".*` or a sub-select alias.
            $plainColumns = preg_match('/^select\s+["`]?\w+["`]?\.\*/i', $top) === 1
                || preg_match('/_count["`]/', $sql) === 1;

            return $aggregate && ! $grouped && $plainColumns;
        }));

        $this->assertSame([], $bad, "MySQL (only_full_group_by) would reject:\n".implode("\n", $bad));
    }

    public function test_inventory_summary_numbers_are_right(): void
    {
        $this->makeProduct(['price' => 1000, 'discount_price' => 800, 'stock' => 3]);
        $this->makeProduct(['price' => 500, 'stock' => 2, 'is_active' => false]);

        $this->actingAs($this->makeAdmin())->get('/admin/inventory')
            ->assertInertia(fn ($page) => $page->component('Admin/Inventory/Index', false)
                ->where('summary.products', 2)
                ->where('summary.units', 5)
                ->where('summary.retail_value', 2400));
    }

    public function test_stock_never_goes_below_zero_on_confirmation(): void
    {
        $customer = $this->makeUser();
        $product = $this->makeProduct(['stock' => 2]);
        $this->addToCart($customer, $product, 2);
        $this->actingAs($customer)->post('/checkout', $this->shipping());

        $this->assertSame(0, $product->fresh()->stock);
    }
}
