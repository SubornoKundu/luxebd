<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use Concerns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['shop.shipping_fee' => 60, 'shop.free_shipping_over' => 5000]);
    }

    public function test_cod_order_deducts_stock_and_clears_cart(): void
    {
        $user = $this->makeUser();
        $a = $this->makeProduct(['price' => 1000, 'discount_price' => 800, 'stock' => 5]);
        $b = $this->makeProduct(['price' => 500, 'stock' => 3]);
        $this->addToCart($user, $a, 2);
        $this->addToCart($user, $b, 1);

        $this->actingAs($user)->post('/checkout', $this->shipping())->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertTrue($order->stock_deducted);
        // 2 x 800 + 500 = 2100 + 60 shipping
        $this->assertEquals(2100, (float) $order->subtotal);
        $this->assertEquals(2160, (float) $order->total_amount);
        $this->assertSame(3, $a->fresh()->stock);
        $this->assertSame(2, $b->fresh()->stock);
        $this->assertSame(0, $user->cartItems()->count());
        $this->assertCount(2, $order->items);
    }

    public function test_free_shipping_over_threshold(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct(['price' => 6000]), 1);

        $this->actingAs($user)->post('/checkout', $this->shipping());

        $this->assertEquals(0, (float) Order::firstOrFail()->shipping_fee);
        $this->assertEquals(6000, (float) Order::firstOrFail()->total_amount);
    }

    public function test_cannot_order_more_than_stock(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock' => 5]);
        $this->addToCart($user, $product, 3);
        $product->update(['stock' => 2]);

        $this->actingAs($user)->post('/checkout', $this->shipping())->assertRedirect('/cart')->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_inactive_product_blocks_checkout(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $this->addToCart($user, $product, 1);
        $product->update(['is_active' => false]);

        $this->actingAs($user)->post('/checkout', $this->shipping())->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_validation_and_empty_cart(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/checkout')->assertRedirect('/cart');
        $this->post('/checkout', [])->assertSessionHasErrors(['shipping_name', 'shipping_phone', 'shipping_address', 'payment_method']);
    }

    public function test_percent_coupon_is_applied_capped_and_counted_once(): void
    {
        Coupon::create(['code' => 'save10', 'type' => 'percent', 'value' => 10, 'min_order_amount' => 500, 'max_discount' => 150, 'usage_limit' => 5, 'once_per_user' => true]);
        $user = $this->makeUser();
        $product = $this->makeProduct(['price' => 2000, 'stock' => 9]);
        $this->addToCart($user, $product, 1);

        $this->actingAs($user)->post('/coupon', ['code' => 'SAVE10'])->assertSessionHas('success');
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', $this->shipping())->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertEquals(150, (float) $order->discount_amount); // 10% = 200 capped at 150
        $this->assertEquals(2000 - 150 + 60, (float) $order->total_amount);
        $this->assertSame(1, Coupon::first()->used_count);

        // Second use by same customer is refused.
        $this->addToCart($user, $product, 1);
        $this->post('/coupon', ['code' => 'SAVE10'])->assertSessionHas('error');
    }

    public function test_coupon_rules(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct(['price' => 400]), 1);

        Coupon::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 100, 'min_order_amount' => 1000]);
        Coupon::create(['code' => 'OLD', 'type' => 'fixed', 'value' => 100, 'expires_at' => now()->subDay()]);
        Coupon::create(['code' => 'SOON', 'type' => 'fixed', 'value' => 100, 'starts_at' => now()->addDay()]);
        Coupon::create(['code' => 'OFF', 'type' => 'fixed', 'value' => 100, 'is_active' => false]);
        Coupon::create(['code' => 'USED', 'type' => 'fixed', 'value' => 100, 'usage_limit' => 1, 'used_count' => 1]);
        Coupon::create(['code' => 'TODAY', 'type' => 'fixed', 'value' => 1000, 'expires_at' => now()]);

        $this->actingAs($user);
        foreach (['BIG', 'OLD', 'SOON', 'OFF', 'USED', 'NOPE'] as $code) {
            $this->post('/coupon', ['code' => $code])->assertSessionHas('error');
        }
        // Valid today (expiry is inclusive); flat discount can't exceed the subtotal.
        $this->post('/coupon', ['code' => 'TODAY'])->assertSessionHas('success');
        $this->post('/checkout', $this->shipping());
        $this->assertEquals(400, (float) Order::firstOrFail()->discount_amount);
        $this->assertEquals(60, (float) Order::firstOrFail()->total_amount);
    }

    public function test_customer_cancel_restores_stock_and_coupon(): void
    {
        Coupon::create(['code' => 'HALF', 'type' => 'percent', 'value' => 50]);
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock' => 5]);
        $this->addToCart($user, $product, 2);
        $this->actingAs($user)->post('/coupon', ['code' => 'HALF']);
        $this->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(1, Coupon::first()->used_count);

        $this->post("/orders/{$order->id}/cancel")->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, Coupon::first()->used_count);

        // Cancelling again does nothing.
        $this->post("/orders/{$order->id}/cancel")->assertSessionHas('error');
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_users_cannot_touch_other_users_orders(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $thief = $this->makeUser('thief@example.com');
        $this->addToCart($owner, $this->makeProduct(), 1);
        $this->actingAs($owner)->post('/checkout', $this->shipping(['payment_method' => 'cod']));
        $order = Order::firstOrFail();

        $this->actingAs($thief);
        $this->get("/orders/{$order->id}")->assertForbidden();
        $this->get("/orders/{$order->id}/invoice")->assertForbidden();
        $this->post("/orders/{$order->id}/cancel")->assertForbidden();

        $this->actingAs($owner)->get("/orders/{$order->id}/invoice")->assertOk()->assertSee($order->order_number);
    }

    public function test_cart_update_for_sold_out_product_removes_line(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock' => 3]);
        $item = $this->addToCart($user, $product, 2);
        $product->update(['stock' => 0]);

        $this->actingAs($user)->patch("/cart/{$item->id}", ['quantity' => 2])->assertSessionHas('error');
        $this->assertSame(0, $user->cartItems()->count());
    }

    public function test_reviews_only_after_delivery(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $this->addToCart($user, $product, 1);
        $this->actingAs($user)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();

        $this->post("/products/{$product->slug}/reviews", ['rating' => 5, 'comment' => 'great'])->assertSessionHas('error');
        $this->assertSame(0, $product->reviews()->count());

        $order->update(['status' => 'completed']);
        $this->post("/products/{$product->slug}/reviews", ['rating' => 5, 'comment' => 'great'])->assertSessionHas('success');
        $this->post("/products/{$product->slug}/reviews", ['rating' => 3, 'comment' => 'ok on reflection'])->assertSessionHas('success');
        $this->post("/products/{$product->slug}/reviews", ['rating' => 9])->assertSessionHasErrors('rating');

        $this->assertSame(1, $product->reviews()->count());
        $this->assertSame(3, $product->reviews()->first()->rating);
        $this->get("/products/{$product->slug}")->assertOk();
    }

    public function test_product_filters(): void
    {
        $this->makeProduct(['name' => 'Cheap One', 'price' => 100]);
        $this->makeProduct(['name' => 'Pricey One', 'price' => 9000]);
        $this->makeProduct(['name' => 'Gone One', 'price' => 500, 'stock' => 0]);

        $this->get('/products?min_price=1000')->assertOk()->assertSee('Pricey One', false)->assertDontSee('Cheap One', false);
        $this->get('/products?max_price=600&in_stock=1')->assertOk()->assertSee('Cheap One', false)->assertDontSee('Gone One', false);
        $this->get('/products?q=100%25')->assertOk();
    }
}
