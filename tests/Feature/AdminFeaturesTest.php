<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminFeaturesTest extends TestCase
{
    use Concerns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['shop.shipping_fee' => 60, 'shop.free_shipping_over' => 5000, 'shop.low_stock_threshold' => 5]);
    }

    protected function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('products.csv', $body);
    }

    protected function placeOrder(string $method = 'cod'): Order
    {
        $user = $this->makeUser('buyer'.uniqid().'@example.com');
        $this->addToCart($user, $this->makeProduct(['price' => 1000, 'stock' => 20]), 1);
        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => $method, 'transaction_id' => 'TRX'.strtoupper(bin2hex(random_bytes(4)))]));

        return Order::latest('id')->firstOrFail();
    }

    public function test_new_admin_pages_are_admin_only(): void
    {
        $order = $this->placeOrder();
        $product = Product::firstOrFail();
        $question = ProductQuestion::create(['product_id' => $product->id, 'user_id' => $order->user_id, 'question' => 'Does it work?']);

        $this->post('/logout');
        foreach (['/admin/inventory', '/admin/questions', "/admin/orders/print?ids={$order->id}", '/admin/products/import-template'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->actingAs($this->makeUser('plain@example.com'));
        foreach (['/admin/inventory', '/admin/questions', "/admin/orders/print?ids={$order->id}", '/admin/products/import-template'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/products/import', ['file' => $this->csv('x')])->assertForbidden();
        $this->post("/admin/inventory/{$product->id}/restock", ['quantity' => 5])->assertForbidden();
        $this->patch("/admin/questions/{$question->id}", ['answer' => 'Yes'])->assertForbidden();
    }

    public function test_csv_import_creates_updates_and_reports_bad_rows(): void
    {
        $this->actingAs($this->makeAdmin());
        $existing = $this->makeProduct(['name' => 'Cotton Polo', 'slug' => 'cotton-polo', 'price' => 500, 'stock' => 1]);

        $csv = "\xEF\xBB\xBFName,Category,Subcategory,Brand,Price,Discount Price,Stock,Description,Image,Featured,Active\n"
            ."Wireless Earbuds,Electronics,Audio,Acme Audio,1999,1599,40,\"Nice, small buds\",,yes,yes\n"
            ."Cotton Polo,Fashion,,Acme,899,,120,Updated desc,/images/custom.jpg,no,no\n"
            ."Broken Row,Electronics,,Acme,abc,,5,,,,\n"
            ."Too Cheap,Electronics,,Acme,100,200,5,,,,\n"
            .",,,,,,,,,,\n"
            ."Second Pair,Electronics,Audio,ACME AUDIO,500,,7,,https://example.com/pic.png,,\n";

        $response = $this->post('/admin/products/import', ['file' => $this->csv($csv)]);
        $response->assertSessionHas('success');

        // 2 created (Earbuds, Second Pair), 1 updated (Polo), 2 skipped.
        $this->assertStringContainsString('2 created, 1 updated, 2 skipped', session('success'));
        $this->assertStringContainsString('Row 4', session('success'));

        $earbuds = Product::where('slug', 'wireless-earbuds')->firstOrFail();
        $this->assertEquals(1599, $earbuds->discount_price);
        $this->assertTrue($earbuds->is_featured);
        $this->assertSame('/images/placeholder.svg', $earbuds->image);
        $this->assertSame('Audio', $earbuds->category->name);
        $this->assertSame('Electronics', $earbuds->category->parent->name);
        $this->assertSame('Nice, small buds', $earbuds->description);

        $polo = $existing->fresh();
        $this->assertSame(120, $polo->stock);
        $this->assertEquals(899, $polo->price);
        $this->assertFalse($polo->is_active);
        $this->assertSame('/images/custom.jpg', $polo->image);

        // Brand and category names are matched case-insensitively, not duplicated.
        $this->assertSame(1, Brand::whereRaw('LOWER(name) = ?', ['acme audio'])->count());
        $this->assertSame(1, Category::where('name', 'Audio')->count());
        $this->assertSame('https://example.com/pic.png', Product::where('slug', 'second-pair')->value('image'));
        $this->assertSame(0, Product::where('name', 'Broken Row')->count());
        $this->assertSame(0, Product::where('name', 'Too Cheap')->count());
    }

    public function test_csv_import_rejects_bad_files(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/products/import', [])->assertSessionHasErrors('file');
        $this->post('/admin/products/import', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');

        $this->post('/admin/products/import', ['file' => $this->csv("title,cost\nA,1\n")])->assertSessionHas('error');
        $this->assertSame(0, Product::count());

        $this->get('/admin/products/import-template')->assertOk()->assertHeader('content-disposition');
    }

    public function test_product_form_saves_variants_specs_and_syncs_stock(): void
    {
        $this->actingAs($this->makeAdmin());
        $cat = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
        $brand = Brand::create(['name' => 'Zed', 'slug' => 'zed']);

        $payload = [
            'name' => 'Runner One', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'price' => 3000, 'stock' => 99,
            'is_featured' => false, 'is_active' => true, 'sync_options' => 1,
            'image' => UploadedFile::fake()->image('p.jpg'),
            'shipping_info' => 'Ships fast.',
            'specifications' => [['label' => 'Weight', 'value' => '250 g'], ['label' => '', 'value' => 'ignored']],
            'variants' => [
                ['size' => '41', 'color' => 'Red', 'extra_price' => 0, 'stock' => 4],
                ['size' => '42', 'color' => 'Red', 'extra_price' => 150, 'stock' => 6, 'image' => UploadedFile::fake()->image('v.jpg')],
                ['size' => '', 'color' => '', 'type' => '', 'stock' => 50],   // empty row is ignored
            ],
        ];

        $this->post('/admin/products', $payload)->assertRedirect('/admin/products');
        $product = Product::where('name', 'Runner One')->firstOrFail();

        $this->assertSame(10, $product->stock);   // 4 + 6, the typed 99 is overridden
        $this->assertSame([['label' => 'Weight', 'value' => '250 g']], $product->specifications);
        $this->assertSame('Ships fast.', $product->shipping_info);
        $this->assertSame(2, $product->variants()->count());
        $with = $product->variants()->where('size', '42')->first();
        $this->assertEquals(150, $with->extra_price);
        $this->assertStringStartsWith('/images/uploads/variants/', $with->image);
        @unlink(public_path(ltrim($with->image, '/')));
        @unlink(public_path(ltrim($product->image, '/')));

        // Edit: change one row, drop the other, add a new one.
        $keep = $product->variants()->where('size', '41')->first();
        $this->post("/admin/products/{$product->id}", [
            '_method' => 'put', 'name' => 'Runner One', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'price' => 3000,
            'stock' => 0, 'is_featured' => false, 'is_active' => true, 'sync_options' => 1,
            'variants' => [
                ['id' => $keep->id, 'size' => '41', 'color' => 'Blue', 'extra_price' => 0, 'stock' => 9],
                ['size' => '43', 'color' => 'Blue', 'extra_price' => 0, 'stock' => 1],
            ],
        ])->assertRedirect('/admin/products');

        $product->refresh();
        $this->assertSame(10, $product->stock);
        $this->assertNull($product->specifications);   // cleared: options section was submitted without specs
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame('Blue', $keep->fresh()->color);
        $this->assertSame(0, $product->variants()->where('size', '42')->count());

        // A form without the options section leaves variants/specs alone.
        $this->post("/admin/products/{$product->id}", [
            '_method' => 'put', 'name' => 'Runner One v2', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'price' => 3000,
            'stock' => 0, 'is_featured' => false, 'is_active' => true,
        ]);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_category_manager_enforces_two_levels(): void
    {
        $this->actingAs($this->makeAdmin());
        $top = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);

        $this->post('/admin/categories', ['name' => 'Shirts', 'parent_id' => $top->id])->assertSessionHasNoErrors();
        $child = Category::where('name', 'Shirts')->firstOrFail();
        $this->assertSame($top->id, $child->parent_id);
        $this->assertSame('shirts', $child->slug);

        // A sub-category cannot be a parent, nothing can be its own parent, parents with children can't move.
        $this->post('/admin/categories', ['name' => 'Polo', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $this->put("/admin/categories/{$top->id}", ['name' => 'Fashion', 'parent_id' => $top->id])->assertSessionHasErrors('parent_id');
        $other = Category::create(['name' => 'Home', 'slug' => 'home']);
        $this->put("/admin/categories/{$top->id}", ['name' => 'Fashion', 'parent_id' => $other->id])->assertSessionHasErrors('parent_id');
        $this->post('/admin/categories', ['name' => 'Ghost', 'parent_id' => 9999])->assertSessionHasErrors('parent_id');

        // Moving a child to top level works; deleting a parent with children is blocked.
        $this->put("/admin/categories/{$child->id}", ['name' => 'Shirts', 'parent_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($child->fresh()->parent_id);
        $this->put("/admin/categories/{$child->id}", ['name' => 'Shirts', 'parent_id' => $top->id]);
        $this->delete("/admin/categories/{$top->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $top->id]);

        $this->get('/admin/categories')->assertInertia(fn ($page) => $page
            ->where('items.data', fn ($rows) => collect($rows)->firstWhere('name', 'Shirts')['parent_name'] === 'Fashion'));

        // Product form lists sub-categories as "Parent › Child".
        $this->get('/admin/products/create')->assertInertia(fn ($page) => $page
            ->where('categories', fn ($cats) => collect($cats)->pluck('name')->contains('Fashion › Shirts')));
    }

    public function test_inventory_page_figures_and_restock(): void
    {
        $this->actingAs($this->makeAdmin());
        $ok = $this->makeProduct(['name' => 'Plenty', 'stock' => 50, 'price' => 100]);
        $low = $this->makeProduct(['name' => 'Low One', 'stock' => 3, 'price' => 200, 'discount_price' => 150]);
        $out = $this->makeProduct(['name' => 'Gone One', 'stock' => 0]);

        $this->get('/admin/inventory')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Inventory/Index', false)
            ->where('summary.products', 3)
            ->where('summary.units', 53)
            ->where('summary.low', 1)
            ->where('summary.out', 1)
            ->where('summary.retail_value', 5450)   // 50*100 + 3*150
            ->has('products.data', 2)               // default view = needs attention
            ->where('products.data.0.level', 'out')
            ->where('products.data.1.level', 'low'));

        $this->get('/admin/inventory?view=all')->assertInertia(fn ($page) => $page->has('products.data', 3));
        $this->get('/admin/inventory?view=all&q=Plen')->assertInertia(fn ($page) => $page->has('products.data', 1));

        $this->post("/admin/inventory/{$low->id}/restock", ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post("/admin/inventory/{$low->id}/restock", ['quantity' => 12])->assertSessionHas('success');
        $this->assertSame(15, $low->fresh()->stock);

        $variantProduct = $this->makeProduct(['stock' => 0]);
        $variantProduct->variants()->create(['size' => 'M', 'stock' => 1]);
        $this->post("/admin/inventory/{$variantProduct->id}/restock", ['quantity' => 5])->assertSessionHas('error');
    }

    public function test_inventory_suggests_restock_from_recent_sales(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock' => 40, 'price' => 100]);
        $this->actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->addToCart($user, $product, 10);
            $this->post('/checkout', $this->shipping());
            $product->update(['stock' => 40 - ($i + 1) * 10 + 30]);   // keep stock healthy between orders
        }
        $product->update(['stock' => 4]);

        $this->actingAs($this->makeAdmin())->get('/admin/inventory')->assertInertia(fn ($page) => $page
            ->where('products.data.0.sold_30d', 30)
            ->where('products.data.0.level', 'low')
            ->where('products.data.0.suggested_restock', 26)   // 30 days of sales (30) minus 4 in stock
            ->where('products.data.0.days_left', 4));
    }

    public function test_packing_slips_print_one_page_per_order(): void
    {
        $a = $this->placeOrder();
        $b = $this->placeOrder('bkash');
        $this->actingAs($this->makeAdmin());

        $this->get("/admin/orders/print?ids={$a->id},{$b->id}")->assertOk()
            ->assertSee($a->order_number)->assertSee($b->order_number)
            ->assertSee('Packing slip')->assertSee('COLLECT');
        $this->get("/admin/orders/print?ids={$a->id}")->assertOk()->assertDontSee($b->order_number);

        $this->get('/admin/orders/print')->assertNotFound();
        $this->get('/admin/orders/print?ids=99999')->assertNotFound();
        $this->get('/admin/orders/print?ids=abc')->assertNotFound();

        // Paid orders say so instead of asking for cash.
        $a->update(['payment_status' => 'paid']);
        $this->get("/admin/orders/print?ids={$a->id}")->assertSee('PAID')->assertDontSee('COLLECT');

        // The customer invoice still works and shows the variant name.
        $this->get("/orders/{$a->id}/invoice")->assertOk()->assertSee($a->order_number);
    }

    public function test_questions_can_be_answered_hidden_and_deleted(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $q = ProductQuestion::create(['product_id' => $product->id, 'user_id' => $user->id, 'question' => 'Is it original?']);
        ProductQuestion::create(['product_id' => $product->id, 'user_id' => $user->id, 'question' => 'Warranty?', 'answer' => '1 year', 'answered_at' => now()]);

        $this->actingAs($this->makeAdmin());
        $this->get('/admin/questions')->assertInertia(fn ($page) => $page->has('questions.data', 2)->where('counts.open', 1));
        $this->get('/admin/questions?status=open')->assertInertia(fn ($page) => $page->has('questions.data', 1));
        $this->get('/admin/questions?status=answered')->assertInertia(fn ($page) => $page->has('questions.data', 1));

        $this->patch("/admin/questions/{$q->id}", ['answer' => ''])->assertSessionHasErrors('answer');
        $this->patch("/admin/questions/{$q->id}", ['answer' => 'Yes, 100% original.'])->assertSessionHas('success');
        $this->assertNotNull($q->fresh()->answered_at);
        $this->get("/products/{$product->slug}")->assertInertia(fn ($page) => $page->has('questions', 2));

        $this->patch("/admin/questions/{$q->id}/toggle");
        $this->get("/products/{$product->slug}")->assertInertia(fn ($page) => $page->has('questions', 1));
        $this->get('/admin/questions?status=hidden')->assertInertia(fn ($page) => $page->has('questions.data', 1));

        $this->delete("/admin/questions/{$q->id}");
        $this->assertDatabaseMissing('product_questions', ['id' => $q->id]);
    }

    public function test_dashboard_daily_summary_chart_series_and_order_filter(): void
    {
        $admin = $this->makeAdmin();
        $paid = $this->placeOrder();
        $paid->update(['payment_status' => 'paid']);
        $second = $this->placeOrder('bkash');
        $cancelled = $this->placeOrder();
        $this->actingAs($cancelled->user)->post("/orders/{$cancelled->id}/cancel");
        $second->update(['status' => 'processing', 'processed_at' => now()]);

        $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
            ->where('daily.today.orders', 2)                  // cancelled order is not a sale
            ->where('daily.today.sales', 2120)                // two orders of 1000 + 60 shipping
            ->where('daily.today.paid', 1060)
            ->where('daily.today.units', 2)
            ->where('daily.today.cancelled', 1)
            ->where('daily.change.sales', null)               // nothing sold yesterday -> no percentage
            ->has('series', 90)
            ->where('series.89.orders', 2)
            ->where('series.0.orders', 0)
            ->has('categorySales', 1)
            ->where('categorySales.0.name', 'Gadgets')
            ->where('categorySales.0.revenue', 2000)
            ->where('recentStatus', 'all')
            ->has('recentOrders', 3)
            ->has('chart', 14));

        $this->get('/admin?status=processing')->assertInertia(fn ($page) => $page
            ->where('recentStatus', 'processing')->has('recentOrders', 1));
        $this->get('/admin?status=pending')->assertInertia(fn ($page) => $page->has('recentOrders', 1));
        $this->get('/admin?status=bogus')->assertInertia(fn ($page) => $page->where('recentStatus', 'all'));
    }

    public function test_admin_sidebar_alert_numbers(): void
    {
        $this->placeOrder();
        $product = Product::firstOrFail();
        $product->update(['stock' => 2]);
        ProductQuestion::create(['product_id' => $product->id, 'user_id' => $this->makeUser('q@example.com')->id, 'question' => 'Any discount?']);

        $this->actingAs($this->makeAdmin())->get('/admin')->assertInertia(fn ($page) => $page
            ->where('adminAlerts.low_stock', 1)
            ->where('adminAlerts.pending_orders', 1)
            ->where('adminAlerts.open_questions', 1));
    }

    public function test_admin_types_the_courier_and_the_customer_sees_it(): void
    {
        $order = $this->placeOrder();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/courier", ['courier' => '  Pathao ', 'tracking_code' => 'PT-123'])
            ->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('Pathao', $order->courier);
        $this->assertSame('PT-123', $order->tracking_code);
        $this->assertNotNull($order->courier_booked_at);

        $this->actingAs($order->user)->get("/orders/{$order->id}")
            ->assertInertia(fn ($p) => $p->where('courier', 'Pathao')->where('order.tracking_code', 'PT-123'));

        // Empty courier clears both. A code without a courier is dropped.
        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/courier", ['courier' => '', 'tracking_code' => 'X1']);
        $order->refresh();
        $this->assertNull($order->courier);
        $this->assertNull($order->tracking_code);

        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/courier", ['courier' => str_repeat('a', 61)])
            ->assertSessionHasErrors('courier');

        // Customers cannot set it.
        $this->actingAs($order->user)->patch("/admin/orders/{$order->id}/courier", ['courier' => 'Mine'])->assertForbidden();
    }

    public function test_check_payment_filter_lists_only_unchecked_bkash_orders(): void
    {
        config(['shop.payment_methods' => ['cod', 'bkash']]);
        $cod = $this->placeOrder('cod');
        $bkash = $this->placeOrder('bkash');
        $paid = $this->placeOrder('bkash');
        $paid->update(['payment_status' => 'paid']);

        $this->actingAs($this->makeAdmin())->get('/admin/orders?check=1')
            ->assertInertia(fn ($p) => $p
                ->where('filters.check', '1')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $bkash->id));
    }
}
