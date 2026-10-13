<?php

namespace Tests\Feature;

use App\Models\Order;
use Database\Seeders\VireluxeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VireluxeTest extends TestCase
{
    use Concerns;
    use RefreshDatabase;

    public function test_checkout_accepts_only_manual_bkash_when_that_is_the_shop_setting(): void
    {
        config(['shop.payment_methods' => ['bkash']]);
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());

        foreach (['cod', 'nagad', 'online'] as $method) {
            $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => $method]))
                ->assertSessionHasErrors('payment_method');
        }
        $this->assertSame(0, Order::count());

        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash']))
            ->assertSessionHasErrors('transaction_id');

        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8n7a6d5e4c']))
            ->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('bkash', $order->payment_method);
        $this->assertSame('8N7A6D5E4C', $order->transaction_id);
        $this->assertSame('pending', $order->payment_status);
    }

    public function test_bkash_advance_splits_payment_between_bkash_and_cash_on_delivery(): void
    {
        config(['shop.payment_methods' => ['bkash'], 'shop.advance_percent' => 50, 'shop.shipping_fee' => 0, 'shop.free_shipping_over' => 0]);
        $user = $this->makeUser();
        $product = $this->makeProduct(['price' => 1001, 'stock' => 5]);
        $this->addToCart($user, $product);

        // Sender number and transaction id are both required and kept separately.
        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C']))
            ->assertSessionHasErrors('payer_number');
        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C', 'payer_number' => '12345']))
            ->assertSessionHasErrors('payer_number');
        $this->assertSame(0, Order::count());

        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C', 'payer_number' => '+880 1712-345678']))
            ->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('01712345678', $order->payer_number);
        $this->assertSame('8N7A6D5E4C', $order->transaction_id);
        $this->assertEquals(501.0, (float) $order->advance_amount);
        $this->assertEquals(500.0, $order->balance_due);

        // Advance verified: still 500 to collect; delivered: nothing left.
        $order->update(['payment_status' => 'paid']);
        $this->assertEquals(500.0, $order->fresh()->balance_due);
        $order->update(['status' => Order::STATUS_COMPLETED]);
        $this->assertEquals(0.0, $order->fresh()->balance_due);
    }

    public function test_advance_is_off_by_default_and_never_applies_to_other_methods(): void
    {
        config(['shop.payment_methods' => ['cod', 'bkash'], 'shop.advance_percent' => 0]);
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());
        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C']))
            ->assertSessionHasNoErrors();
        $this->assertEquals(0.0, (float) Order::firstOrFail()->advance_amount);

        config(['shop.advance_percent' => 50]);
        $cod = $this->makeUser('cod-buyer@example.com');
        $this->addToCart($cod, $this->makeProduct());
        $this->actingAs($cod)->post('/checkout', $this->shipping(['payment_method' => 'cod']))->assertSessionHasNoErrors();
        $order = Order::where('payment_method', 'cod')->firstOrFail();
        $this->assertEquals(0.0, (float) $order->advance_amount);
        $this->assertEquals((float) $order->total_amount, $order->balance_due);
    }

    public function test_cash_balance_is_collected_when_an_advance_order_is_delivered(): void
    {
        config(['shop.payment_methods' => ['bkash'], 'shop.advance_percent' => 50]);
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());
        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C', 'payer_number' => '01712345678']));
        $order = Order::firstOrFail();

        app(\App\Services\OrderService::class)->updateStatus($order, Order::STATUS_PROCESSING);
        app(\App\Services\OrderService::class)->updateStatus($order->fresh(), Order::STATUS_COMPLETED);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertEquals(0.0, $order->fresh()->balance_due);
    }

    public function test_only_the_configured_order_events_send_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        config(['shop.payment_methods' => ['bkash'], 'shop.order_emails' => ['processing']]);
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());

        $this->actingAs($user)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C']));
        \Illuminate\Support\Facades\Mail::assertNothingQueued();
        \Illuminate\Support\Facades\Mail::assertNothingSent();

        app(\App\Services\OrderService::class)->updateStatus(Order::firstOrFail(), Order::STATUS_PROCESSING);
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\OrderStatusMail::class, 1);
    }

    public function test_checkout_page_offers_bkash_with_the_shop_number(): void
    {
        config(['shop.payment_methods' => ['bkash'], 'shop.payments.bkash.number' => '01860291022']);
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());

        $this->actingAs($user)->get('/checkout')->assertInertia(fn ($p) => $p
            ->where('paymentMethods', ['bkash'])
            ->where('manualPayments.bkash.number', '01860291022'));
    }

    public function test_vireluxe_seeder_builds_a_working_catalogue_without_fake_orders(): void
    {
        $this->seed(VireluxeSeeder::class);
        $this->seed(VireluxeSeeder::class); // safe to run twice

        $this->get('/')->assertOk();
        $this->get('/products')->assertOk();
        $this->assertSame(0, Order::count());
        $this->assertSame('01860291022', \App\Services\SiteSettings::all()['bkash_number']);
    }

    public function test_home_page_groups_perfumes_by_brand_gender_and_price(): void
    {
        $this->seed(VireluxeSeeder::class);
        config(['shop.premium_from' => 4000]);

        $this->get('/')->assertInertia(fn ($p) => $p
            ->has('brands', 8)
            ->where('genderSections.0.slug', 'for-him')
            ->where('genderSections.1.slug', 'unisex')
            ->where('premiumFrom', 4000)
            ->has('premiumProducts')
            ->has('affordableProducts'));

        // Brand and price filters used by the home page links.
        $this->get('/products?brand=dior')->assertOk();
        // The invented samples of the first version are gone; the client's perfumes are in.
        $this->assertDatabaseMissing('products', ['slug' => 'rose-atelier']);
        $this->assertDatabaseHas('products', ['slug' => 'sauvage-parfum']);
        $this->assertSame(1, \App\Models\Product::where('slug', 'sauvage-parfum')->firstOrFail()->images()->count());

        $this->get('/products?min_price=4000&sort=price_desc')->assertOk();
        $this->get('/products?max_price=3999&sort=price_asc')->assertOk();
    }
}
