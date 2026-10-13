<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFeaturesTest extends TestCase
{
    use Concerns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['shop.shipping_fee' => 60, 'shop.free_shipping_over' => 5000]);
    }

    /** @return array{0: Product, 1: ProductVariant, 2: ProductVariant} */
    protected function variantProduct(): array
    {
        $product = $this->makeProduct(['price' => 1000, 'discount_price' => 900, 'stock' => 0]);
        $small = $product->variants()->create(['size' => 'S', 'color' => 'Black', 'stock' => 3]);
        $large = $product->variants()->create(['size' => 'L', 'color' => 'Black', 'extra_price' => 100, 'stock' => 5]);
        $product->syncStockFromVariants();

        return [$product->fresh(), $small, $large];
    }

    public function test_sub_category_products_show_under_the_parent_category(): void
    {
        $parent = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $child = Category::create(['name' => 'Phones', 'slug' => 'phones', 'parent_id' => $parent->id]);
        $other = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);

        $this->makeProduct(['name' => 'Direct Gadget', 'category_id' => $parent->id]);
        $this->makeProduct(['name' => 'Child Phone', 'category_id' => $child->id]);
        $this->makeProduct(['name' => 'Some Boot', 'category_id' => $other->id]);

        $this->get('/products?category=electronics')->assertOk()
            ->assertSee('Direct Gadget', false)->assertSee('Child Phone', false)->assertDontSee('Some Boot', false);
        $this->get('/products?category=phones')->assertOk()
            ->assertSee('Child Phone', false)->assertDontSee('Direct Gadget', false);

        // The header menu gets the two-level tree.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('navCategories', 3)
            ->where('navCategories.0.slug', 'electronics')
            ->has('navCategories.0.children', 1));
    }

    public function test_popular_sort_puts_best_sellers_first_and_ignores_cancelled_orders(): void
    {
        $user = $this->makeUser();
        $slow = $this->makeProduct(['name' => 'Slow Seller']);
        $hit = $this->makeProduct(['name' => 'Hit Seller']);
        $cancelledHit = $this->makeProduct(['name' => 'Cancelled Hit']);

        $line = fn (Order $o, Product $p, int $qty) => OrderItem::create([
            'order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name,
            'price' => 1000, 'quantity' => $qty, 'subtotal' => 1000 * $qty,
        ]);
        $make = fn (string $status) => Order::create([
            'user_id' => $user->id, 'order_number' => Order::generateOrderNumber(), 'subtotal' => 1, 'total_amount' => 1,
            'status' => $status, 'shipping_name' => 'a', 'shipping_phone' => '1', 'shipping_address' => 'x', 'shipping_city' => 'y',
        ]);

        $line($make('completed'), $slow, 1);
        $line($make('completed'), $hit, 9);
        $line($make('cancelled'), $cancelledHit, 50);

        $this->get('/products?sort=popular')->assertOk()->assertInertia(fn ($page) => $page
            ->where('products.data.0.name', 'Hit Seller')
            ->where('products.data.1.name', 'Slow Seller'));

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('popularProducts', 2)
            ->where('popularProducts.0.name', 'Hit Seller'));
    }

    public function test_live_search_suggestions(): void
    {
        $this->makeProduct(['name' => 'Galaxy Phone Pro']);
        $this->makeProduct(['name' => 'Galaxy Hidden', 'is_active' => false]);
        $this->makeProduct(['name' => 'Kitchen Mixer']);

        $this->getJson('/search/suggest?q=g')->assertOk()->assertJson(['total' => 0, 'products' => []]);

        $this->getJson('/search/suggest?q=galaxy')->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Galaxy Phone Pro')
            ->assertJsonPath('total', 1);

        // LIKE wildcards are escaped, not interpreted.
        $this->getJson('/search/suggest?q=%25%25')->assertOk()->assertJson(['total' => 0]);
    }

    public function test_product_page_has_variants_specs_questions_and_bundle(): void
    {
        [$product, $small] = $this->variantProduct();
        $product->update([
            'specifications' => [['label' => 'Fabric', 'value' => 'Cotton']],
            'shipping_info' => 'Ships in 2 days.',
        ]);
        $asker = $this->makeUser();
        ProductQuestion::create(['product_id' => $product->id, 'user_id' => $asker->id, 'question' => 'Does it shrink?', 'answer' => 'No.', 'answered_at' => now()]);
        ProductQuestion::create(['product_id' => $product->id, 'user_id' => $asker->id, 'question' => 'Hidden one?', 'is_published' => false]);
        $mate = $this->makeProduct(['name' => 'Bundle Mate', 'category_id' => $product->category_id]);

        $this->get("/products/{$product->slug}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Products/Show', false)
            ->has('product.variants', 2)
            ->where('product.variants.0.label', 'Size S / Black')
            ->where('product.specifications.0.label', 'Fabric')
            ->where('shippingInfo', 'Ships in 2 days.')
            ->has('questions', 1)
            ->where('questions.0.answer', 'No.')
            ->has('bundle', 1)
            ->where('bundle.0.id', $mate->id));
    }

    public function test_cart_needs_a_variant_and_respects_variant_stock(): void
    {
        [$product, $small, $large] = $this->variantProduct();
        $user = $this->makeUser();
        $this->actingAs($user);

        // No option chosen -> refused.
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertSessionHas('error');
        $this->assertSame(0, CartItem::count());

        // Option from another product -> refused.
        $foreign = $this->makeProduct();
        $foreignVariant = $foreign->variants()->create(['size' => 'M', 'stock' => 2]);
        $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $foreignVariant->id])->assertSessionHas('error');

        // Quantity is capped by the *variant's* stock (3), not the product total (8).
        $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $small->id, 'quantity' => 10])->assertSessionHas('success');
        $this->assertSame(3, CartItem::where('variant_id', $small->id)->value('quantity'));

        // A different variant of the same product is its own line.
        $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $large->id, 'quantity' => 2]);
        $this->assertSame(2, CartItem::count());

        $line = CartItem::where('variant_id', $large->id)->first();
        $this->assertSame(1000.0, $line->unit_price);   // 900 sale + 100 surcharge
        $this->assertSame(2000.0, $line->line_total);

        // Cart drawer data is shared on every page.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('cartCount', 5)
            ->has('cartDrawer.items', 2)
            ->where('cartDrawer.subtotal', 4700));
    }

    public function test_variant_order_takes_and_returns_variant_stock(): void
    {
        [$product, $small, $large] = $this->variantProduct();
        $user = $this->makeUser();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'variant_id' => $large->id, 'quantity' => 2]);

        $this->post('/checkout', $this->shipping())->assertRedirect();

        $order = Order::firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame($large->id, $item->variant_id);
        $this->assertSame('Size L / Black', $item->variant_label);
        $this->assertEquals(1000, $item->price);
        $this->assertSame(3, $large->fresh()->stock);
        $this->assertSame(3, $small->fresh()->stock);
        $this->assertSame(6, $product->fresh()->stock);
        $this->assertSame(0, $user->cartItems()->count());

        $this->post("/orders/{$order->id}/cancel")->assertSessionHas('success');
        $this->assertSame(5, $large->fresh()->stock);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_variant_sold_out_after_adding_blocks_checkout(): void
    {
        [$product, $small] = $this->variantProduct();
        $user = $this->makeUser();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'variant_id' => $small->id, 'quantity' => 3]);
        $small->update(['stock' => 1]);

        $this->post('/checkout', $this->shipping())->assertRedirect('/cart')->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_buy_now_goes_straight_to_checkout_and_bundle_adds_all(): void
    {
        $user = $this->makeUser();
        $a = $this->makeProduct();
        $b = $this->makeProduct();
        $this->actingAs($user);

        $this->post('/cart', ['product_id' => $a->id, 'quantity' => 2, 'buy_now' => true])->assertRedirect('/checkout');
        $this->assertSame(2, CartItem::where('product_id', $a->id)->value('quantity'));

        $this->post('/cart/bundle', ['items' => [['product_id' => $a->id], ['product_id' => $b->id]]])->assertSessionHas('success');
        $this->assertSame(3, CartItem::where('product_id', $a->id)->value('quantity'));
        $this->assertSame(1, CartItem::where('product_id', $b->id)->value('quantity'));

        $this->post('/cart/bundle', ['items' => []])->assertSessionHasErrors('items');
    }

    public function test_guests_are_sent_to_login_for_cart_and_questions(): void
    {
        $product = $this->makeProduct();

        $this->post('/cart', ['product_id' => $product->id])->assertRedirect('/login');
        $this->post("/products/{$product->slug}/questions", ['question' => 'Is it good?'])->assertRedirect('/login');
    }

    public function test_customers_can_ask_questions_with_a_daily_limit(): void
    {
        $product = $this->makeProduct();
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post("/products/{$product->slug}/questions", ['question' => 'abc'])->assertSessionHasErrors('question');

        for ($i = 1; $i <= 5; $i++) {
            $this->post("/products/{$product->slug}/questions", ['question' => "Question number {$i}?"])->assertSessionHas('success');
        }

        $this->post("/products/{$product->slug}/questions", ['question' => 'One too many?'])->assertSessionHas('error');
        $this->assertSame(5, ProductQuestion::count());
    }

    public function test_recommended_bundle_prefers_products_bought_together(): void
    {
        $user = $this->makeUser();
        $main = $this->makeProduct(['name' => 'Main Item']);
        $partner = $this->makeProduct(['name' => 'Partner Item', 'category_id' => Category::create(['name' => 'Else', 'slug' => 'else'])->id]);
        $this->makeProduct(['name' => 'Same Category Filler', 'category_id' => $main->category_id]);

        $order = Order::create([
            'user_id' => $user->id, 'order_number' => Order::generateOrderNumber(), 'subtotal' => 1, 'total_amount' => 1,
            'shipping_name' => 'a', 'shipping_phone' => '1', 'shipping_address' => 'x', 'shipping_city' => 'y',
        ]);
        foreach ([$main, $partner] as $p) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'product_name' => $p->name, 'price' => 1, 'quantity' => 1, 'subtotal' => 1]);
        }

        $this->get("/products/{$main->slug}")->assertInertia(fn ($page) => $page
            ->has('bundle', 2)
            ->where('bundle.0.name', 'Partner Item')
            ->where('bundle.1.name', 'Same Category Filler'));
    }
}
