<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use Concerns, RefreshDatabase;

    /** @var list<string> */
    protected array $uploaded = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // Remove only the files this test created.
        foreach ($this->uploaded as $path) {
            @unlink(public_path(ltrim($path, '/')));
        }

        parent::tearDown();
    }

    protected function track(?string $path): ?string
    {
        if ($path) {
            $this->uploaded[] = $path;
        }

        return $path;
    }

    public function test_admin_area_is_protected(): void
    {
        $urls = ['/admin', '/admin/products', '/admin/products/create', '/admin/categories', '/admin/brands', '/admin/sliders',
            '/admin/coupons', '/admin/orders', '/admin/customers', '/admin/reviews', '/admin/inventory', '/admin/questions', '/admin/settings', '/admin/admins', '/admin/admins'];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $product = $this->makeProduct();
        $this->actingAs($this->makeUser());
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/categories', ['name' => 'Hack'])->assertForbidden();
        $this->delete("/admin/products/{$product->id}")->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('categories', ['name' => 'Hack']);
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeUser();
        $product = $this->makeProduct();
        $this->addToCart($customer, $product, 1);
        $this->actingAs($customer)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();
        Review::create(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 4]);

        $this->actingAs($admin);
        foreach (['/admin', '/admin/products', '/admin/products/create', "/admin/products/{$product->id}/edit", '/admin/categories',
            '/admin/brands', '/admin/sliders', '/admin/coupons', '/admin/orders', "/admin/orders/{$order->id}",
            '/admin/customers', "/admin/customers/{$customer->id}", '/admin/reviews', "/orders/{$order->id}/invoice",
            '/admin/inventory', '/admin/inventory?view=all', '/admin/questions', '/admin/settings', '/admin/admins', "/admin/orders/print?ids={$order->id}", '/admin/products/import-template',
            '/admin/products?status=low&q=ph', '/admin/orders?status=pending&payment=pending&method=cod&q=Buyer'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_every_rendered_inertia_page_has_a_vue_file(): void
    {
        $missing = [];

        foreach (glob(app_path('Http/Controllers/{,*/}*.php'), GLOB_BRACE) as $file) {
            preg_match_all("/Inertia::render\\('([^']+)'/", file_get_contents($file), $m);

            foreach ($m[1] as $component) {
                if (! file_exists(resource_path("js/Pages/{$component}.vue"))) {
                    $missing[] = $component;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_dashboard_numbers(): void
    {
        $customer = $this->makeUser();
        $product = $this->makeProduct(['price' => 1000, 'stock' => 3]);
        $this->addToCart($customer, $product, 1);
        $this->actingAs($customer)->post('/checkout', $this->shipping());
        Order::firstOrFail()->update(['payment_status' => 'paid']);

        $this->actingAs($this->makeAdmin())->get('/admin')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard', false)
                ->where('stats.orders', 1)
                ->where('stats.customers', 1)
                ->where('stats.low_stock', 1)
                ->where('stats.revenue', 1060)
                ->has('chart', 14));
    }

    public function test_product_crud_with_image_upload(): void
    {
        $this->actingAs($this->makeAdmin());
        $cat = Category::create(['name' => 'Phones', 'slug' => 'phones']);
        $brand = Brand::create(['name' => 'Zed', 'slug' => 'zed']);

        $payload = [
            'name' => 'Zed One', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'description' => 'Nice',
            'price' => 5000, 'discount_price' => 4500, 'stock' => 12, 'is_featured' => true, 'is_active' => true,
        ];

        $this->post('/admin/products', $payload)->assertSessionHasErrors('image');
        $this->post('/admin/products', array_merge($payload, ['discount_price' => 6000, 'image' => UploadedFile::fake()->image('p.jpg')]))
            ->assertSessionHasErrors('discount_price');

        $this->post('/admin/products', array_merge($payload, ['image' => UploadedFile::fake()->image('p.jpg', 400, 400)]))
            ->assertRedirect('/admin/products');

        $product = Product::where('name', 'Zed One')->firstOrFail();
        $this->track($product->image);
        $this->assertSame('zed-one', $product->slug);
        $this->assertStringStartsWith('/images/uploads/products/', $product->image);
        $this->assertFileExists(public_path(ltrim($product->image, '/')));
        $this->assertTrue($product->is_featured);

        // Same name -> unique slug.
        $this->post('/admin/products', array_merge($payload, ['image' => UploadedFile::fake()->image('q.png')]));
        $second = Product::where('slug', 'zed-one-2')->firstOrFail();
        $this->track($second->image);

        // Update without a new image keeps the old one; with one replaces it.
        $old = $product->image;
        $this->put("/admin/products/{$product->id}", array_merge($payload, ['name' => 'Zed One Pro', 'stock' => 0]))
            ->assertRedirect('/admin/products');
        $product->refresh();
        $this->assertSame($old, $product->image);
        $this->assertSame('zed-one', $product->slug);
        $this->assertSame(0, $product->stock);

        $this->put("/admin/products/{$product->id}", array_merge($payload, ['image' => UploadedFile::fake()->image('n.webp')]));
        $product->refresh();
        $this->track($product->image);
        $this->assertNotSame($old, $product->image);
        $this->assertFileDoesNotExist(public_path(ltrim($old, '/')));

        // Non-image upload rejected.
        $this->put("/admin/products/{$product->id}", array_merge($payload, ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('image');

        $this->delete("/admin/products/{$product->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertFileDoesNotExist(public_path(ltrim($product->image, '/')));
    }

    public function test_seeded_images_are_never_deleted(): void
    {
        $this->actingAs($this->makeAdmin());
        $product = $this->makeProduct(['image' => '/images/vireluxe/products/rayhaan-lion.jpg']);

        $this->delete("/admin/products/{$product->id}");

        $this->assertFileExists(public_path('images/vireluxe/products/rayhaan-lion.jpg'));
    }

    public function test_category_brand_crud_and_delete_protection(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/categories', ['name' => 'Toys'])->assertSessionHas('success');
        $this->post('/admin/categories', ['name' => 'Toys'])->assertSessionHasErrors('name');
        $category = Category::where('name', 'Toys')->firstOrFail();
        $this->assertSame('toys', $category->slug);

        $this->put("/admin/categories/{$category->id}", ['name' => 'Toys & Games'])->assertSessionHas('success');
        $this->assertSame('Toys & Games', $category->fresh()->name);

        $this->post('/admin/brands', ['name' => 'Lego', 'logo' => UploadedFile::fake()->image('l.png')])->assertSessionHas('success');
        $brand = Brand::where('name', 'Lego')->firstOrFail();
        $this->track($brand->logo);
        $this->assertFileExists(public_path(ltrim($brand->logo, '/')));

        $this->makeProduct(['category_id' => $category->id, 'brand_id' => $brand->id]);
        $this->delete("/admin/categories/{$category->id}")->assertSessionHas('error');
        $this->delete("/admin/brands/{$brand->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        Product::query()->delete();
        $this->delete("/admin/brands/{$brand->id}")->assertSessionHas('success');
        $this->assertFileDoesNotExist(public_path(ltrim($brand->logo, '/')));
        $this->delete("/admin/categories/{$category->id}")->assertSessionHas('success');
    }

    public function test_slider_and_coupon_crud(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/sliders', ['title' => 'Sale'])->assertSessionHasErrors('image');
        $this->post('/admin/sliders', ['title' => 'Big Sale', 'image' => UploadedFile::fake()->image('b.jpg'), 'is_active' => true, 'sort_order' => 2])
            ->assertSessionHas('success');
        $slider = Slider::firstOrFail();
        $this->track($slider->image);
        $this->put("/admin/sliders/{$slider->id}", ['title' => 'Huge Sale', 'is_active' => false])->assertSessionHas('success');
        $this->assertSame('Huge Sale', $slider->fresh()->title);
        $this->assertSame($slider->image, $slider->fresh()->image);
        $this->delete("/admin/sliders/{$slider->id}");
        $this->assertSame(0, Slider::count());

        $this->post('/admin/coupons', ['code' => 'bad code', 'type' => 'percent', 'value' => 10])->assertSessionHasErrors('code');
        $this->post('/admin/coupons', ['code' => 'x1', 'type' => 'percent', 'value' => 150])->assertSessionHasErrors('value');
        $this->post('/admin/coupons', ['code' => 'x1', 'type' => 'fixed', 'value' => 5, 'starts_at' => '2026-05-10', 'expires_at' => '2026-05-01'])
            ->assertSessionHasErrors('expires_at');

        $this->post('/admin/coupons', ['code' => 'eid20', 'type' => 'percent', 'value' => 20, 'is_active' => true, 'once_per_user' => true])
            ->assertSessionHas('success');
        $coupon = Coupon::firstOrFail();
        $this->assertSame('EID20', $coupon->code);
        $this->assertSame('20% off', $coupon->value_label);
        $this->post('/admin/coupons', ['code' => 'EID20', 'type' => 'fixed', 'value' => 5])->assertSessionHasErrors('code');

        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'EID20', 'type' => 'fixed', 'value' => 250, 'is_active' => false])->assertSessionHas('success');
        $this->assertSame('fixed', $coupon->fresh()->type);
        $this->assertFalse($coupon->fresh()->is_active);
        $this->delete("/admin/coupons/{$coupon->id}");
        $this->assertSame(0, Coupon::count());
    }

    public function test_order_workflow_cod(): void
    {
        $customer = $this->makeUser();
        $product = $this->makeProduct(['stock' => 5]);
        $this->addToCart($customer, $product, 2);
        $this->actingAs($customer)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();

        $this->actingAs($this->makeAdmin());
        // Can't skip straight to a nonsense state.
        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'pending'])->assertSessionHasErrors('status');

        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertSessionHas('success');
        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'shipped'])->assertSessionHas('success');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'completed'])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status, 'COD is paid on delivery');
        $this->assertNotNull($order->delivered_at);

        // Final states are final.
        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertSessionHas('error');
        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHas('error');
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_admin_cancel_restores_stock_and_refunds_paid_order(): void
    {
        $customer = $this->makeUser();
        $product = $this->makeProduct(['stock' => 5]);
        $this->addToCart($customer, $product, 2);
        $this->actingAs($customer)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => 'REFUND1234']));
        $order = Order::firstOrFail();
        $this->assertSame(3, $product->fresh()->stock);

        $this->actingAs($this->makeAdmin())->patch("/admin/orders/{$order->id}/payment", ['payment_status' => 'paid'])->assertSessionHas('success');
        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->patch("/admin/orders/{$order->id}/payment", ['payment_status' => 'paid'])->assertSessionHas('error');
    }

    public function test_customer_block_and_review_moderation(): void
    {
        $customer = $this->makeUser();
        $admin = $this->makeAdmin();
        $product = $this->makeProduct();
        $review = Review::create(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 1, 'comment' => 'spam']);

        $this->actingAs($admin);
        $this->patch("/admin/customers/{$customer->id}/block")->assertSessionHas('success');
        $this->assertTrue($customer->fresh()->is_blocked);
        $this->patch("/admin/customers/{$admin->id}/block")->assertForbidden();
        $this->get("/admin/customers/{$admin->id}")->assertNotFound();
        $this->patch("/admin/customers/{$customer->id}/block");
        $this->assertFalse($customer->fresh()->is_blocked);

        $this->patch("/admin/reviews/{$review->id}")->assertSessionHas('success');
        $this->assertFalse($review->fresh()->is_approved);
        $this->assertSame(0, $product->approvedReviews()->count());
        $this->delete("/admin/reviews/{$review->id}");
        $this->assertSame(0, Review::count());
    }

    public function test_admin_search_inputs_are_safe(): void
    {
        $this->actingAs($this->makeAdmin());

        foreach (['/admin/products?q=%25_%5C', '/admin/orders?q=%25', '/admin/customers?q=%27%22', '/admin/coupons?q=%25'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertSame(1, User::where('role', 'admin')->count());
    }
}
