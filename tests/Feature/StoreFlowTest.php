<?php

namespace Tests\Feature;

use App\Mail\LowStockMail;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StoreFlowTest extends TestCase
{
    use Concerns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['shop.shipping_fee' => 60, 'shop.free_shipping_over' => 5000, 'shop.low_stock_threshold' => 5]);
    }

    public function test_one_page_checkout_needs_only_name_phone_and_address(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct(), 1);

        $this->actingAs($user)->post('/checkout', [
            'shipping_name' => 'Buyer', 'shipping_phone' => '01711111111',
            'shipping_address' => 'House 1, Road 2, Mirpur', 'payment_method' => 'cod',
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('', $order->shipping_city);
        $this->assertSame('cod', $order->payment_method);
    }

    public function test_bkash_and_nagad_are_manual_payments_that_need_a_transaction_id(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock' => 5]);
        $this->addToCart($user, $product, 2);
        $this->actingAs($user);

        $this->post('/checkout', $this->shipping(['payment_method' => 'bkash']))->assertSessionHasErrors('transaction_id');
        $this->assertSame(0, Order::count());

        $this->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => ' 9ab7c6d5e4 ']))->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('bkash', $order->payment_method);
        $this->assertSame('9AB7C6D5E4', $order->transaction_id);
        $this->assertSame('pending', $order->payment_status);   // admin confirms the money later
        $this->assertTrue($order->stock_deducted);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, CartItem::count());

        $this->get("/orders/{$order->id}/success")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Orders/Success', false)->where('order.id', $order->id));

        // Nagad works the same way.
        $this->addToCart($user, $product, 1);
        $this->post('/checkout', $this->shipping(['payment_method' => 'nagad', 'transaction_id' => 'NG123456']))->assertRedirect();
        $this->assertSame('nagad', Order::latest('id')->first()->payment_method);
    }

    public function test_success_page_is_private_to_the_buyer(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser('other@example.com');
        $this->addToCart($owner, $this->makeProduct(), 1);
        $this->actingAs($owner)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();

        $this->actingAs($stranger)->get("/orders/{$order->id}/success")->assertForbidden();
    }

    public function test_order_tracking_steps_follow_the_status(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct(), 1);
        $this->actingAs($user)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();

        $done = fn (Order $o) => collect($o->tracking_steps)->where('done', true)->pluck('key')->all();
        $this->assertSame(['placed'], $done($order));

        $this->actingAs($this->makeAdmin())->patch("/admin/orders/{$order->id}/status", ['status' => 'processing']);
        $order->refresh();
        $this->assertNotNull($order->processed_at);
        $this->assertSame(['placed', 'processing'], $done($order));

        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'shipped']);
        $this->patch("/admin/orders/{$order->id}/status", ['status' => 'completed']);
        $order->refresh();
        $this->assertSame(['placed', 'processing', 'shipped', 'completed'], $done($order));
        $this->assertNotNull($order->shipped_at);

        $this->actingAs($user)->get("/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->where('order.tracking_steps.3.done', true));
    }

    public function test_cancelled_order_shows_a_cancelled_step(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct(), 1);
        $this->actingAs($user)->post('/checkout', $this->shipping());
        $order = Order::firstOrFail();
        $this->post("/orders/{$order->id}/cancel");

        $keys = collect($order->refresh()->tracking_steps)->where('done', true)->pluck('key')->all();
        $this->assertSame(['placed', 'cancelled'], $keys);
    }

    public function test_address_book_crud_and_ownership(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other@example.com');
        $this->actingAs($user);

        $payload = ['label' => 'Home', 'name' => 'Buyer', 'phone' => '01711111111', 'address' => 'House 1, Road 2', 'city' => 'Dhaka'];

        $this->post('/account/addresses', ['label' => ''])->assertSessionHasErrors(['label', 'name', 'phone', 'address']);
        $this->post('/account/addresses', $payload)->assertSessionHas('success');
        $this->post('/account/addresses', array_merge($payload, ['label' => 'Office', 'address' => 'Banani']));

        $home = Address::where('label', 'Home')->first();
        $office = Address::where('label', 'Office')->first();
        $this->assertTrue($home->is_default);     // the first one is the default
        $this->assertFalse($office->is_default);

        $this->patch("/account/addresses/{$office->id}/default");
        $this->assertTrue($office->fresh()->is_default);
        $this->assertFalse($home->fresh()->is_default);

        $this->put("/account/addresses/{$office->id}", array_merge($payload, ['label' => 'Work', 'address' => 'Gulshan']));
        $this->assertSame('Gulshan', $office->fresh()->address);

        $this->get('/account/addresses')->assertOk()->assertInertia(fn ($page) => $page->has('addresses', 2)->where('addresses.0.label', 'Work'));

        // Someone else's address cannot be touched.
        $this->actingAs($other);
        $this->put("/account/addresses/{$home->id}", $payload)->assertForbidden();
        $this->patch("/account/addresses/{$home->id}/default")->assertForbidden();
        $this->delete("/account/addresses/{$home->id}")->assertForbidden();

        // Deleting the default promotes another address.
        $this->actingAs($user)->delete("/account/addresses/{$office->id}");
        $this->assertTrue($home->fresh()->is_default);
    }

    public function test_checkout_prefills_from_default_address_and_can_save_a_new_one(): void
    {
        $user = $this->makeUser();
        $user->addresses()->create(['label' => 'Home', 'name' => 'Saved Name', 'phone' => '01899999999', 'address' => 'Saved Road', 'city' => 'Dhaka', 'is_default' => true]);
        $this->addToCart($user, $this->makeProduct(), 1);

        $this->actingAs($user)->get('/checkout')->assertInertia(fn ($page) => $page
            ->has('addresses', 1)
            ->where('defaults.shipping_name', 'Saved Name')
            ->where('defaults.shipping_address', 'Saved Road')
            ->has('manualPayments.bkash.number'));

        $this->post('/checkout', $this->shipping(['shipping_address' => 'Brand New Road', 'save_address' => true]));
        $this->assertSame(2, $user->addresses()->count());

        // Same address again is not duplicated.
        $this->addToCart($user, $this->makeProduct(), 1);
        $this->post('/checkout', $this->shipping(['shipping_address' => 'Brand New Road', 'save_address' => true]));
        $this->assertSame(2, $user->addresses()->count());
    }

    public function test_shared_props_for_header_and_footer(): void
    {
        config(['shop.whatsapp' => '8801811111111']);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('shop.whatsapp', '8801811111111')
            ->has('shop.contact.phone')
            ->has('shop.social')
            ->where('cartDrawer.subtotal', 0)
            ->where('adminAlerts', null));
    }

    public function test_low_stock_alert_mail_goes_to_admins_when_stock_crosses_the_threshold(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock' => 7]);

        $this->addToCart($user, $product, 1);   // 7 -> 6, still above the alert level of 5
        $this->actingAs($user)->post('/checkout', $this->shipping());
        Mail::assertNotQueued(LowStockMail::class);

        $this->addToCart($user, $product, 2);   // 6 -> 4, crosses it
        $this->post('/checkout', $this->shipping());
        Mail::assertQueued(LowStockMail::class, fn ($m) => $m->hasTo($admin->email) && $m->products->first()->id === $product->id);

        $this->addToCart($user, $product, 1);   // 4 -> 3, already low: no second mail
        $this->post('/checkout', $this->shipping());
        Mail::assertQueued(LowStockMail::class, 1);
    }
}
