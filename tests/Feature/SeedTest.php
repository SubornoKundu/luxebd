<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_seed_loads_only_the_real_catalogue_and_runs_twice(): void
    {
        $this->seed();
        $this->seed(); // re-running must not duplicate or crash

        $this->assertGreaterThan(0, Brand::count());
        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, Product::count());
        $this->assertSame(Product::count(), Product::distinct('slug')->count('slug'));

        // No made-up people, orders or coupons come with the shop, and nobody is an admin yet.
        $this->assertSame(0, User::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Coupon::count());

        foreach (['/', '/products', '/products?sort=popular', '/products?min_price=100&max_price=900000', '/search/suggest?q=a', '/login', '/admin/login'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/products/'.Product::first()->slug)->assertOk();
    }

    public function test_an_admin_made_with_the_command_can_open_every_admin_page(): void
    {
        $this->seed();
        $this->artisan('shop:admin', ['email' => 'owner@example.com', '--password' => 'LongPassw0rd!'])->assertSuccessful();
        $admin = User::where('email', 'owner@example.com')->firstOrFail();

        foreach (['/admin', '/admin/orders', '/admin/products', '/admin/categories', '/admin/brands', '/admin/inventory', '/admin/questions', '/admin/coupons', '/admin/sliders', '/admin/customers', '/admin/admins'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
