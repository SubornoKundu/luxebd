<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use Concerns;
    use RefreshDatabase;

    public function test_every_page_carries_the_browser_security_headers(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');

        $this->get('/admin/login')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_one_transaction_id_cannot_pay_for_two_orders(): void
    {
        config(['shop.payment_methods' => ['bkash'], 'shop.advance_percent' => 0]);

        $first = $this->makeUser('first@example.com');
        $this->addToCart($first, $this->makeProduct());
        $this->actingAs($first)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => '8N7A6D5E4C']))
            ->assertSessionHasNoErrors();

        $second = $this->makeUser('second@example.com');
        $this->addToCart($second, $this->makeProduct());
        $this->actingAs($second)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => ' 8n7a6d5e4c ']))
            ->assertSessionHasErrors('transaction_id');
        $this->assertSame(1, Order::count());
    }

    public function test_someone_elses_order_cannot_be_opened(): void
    {
        config(['shop.payment_methods' => ['bkash']]);
        $owner = $this->makeUser('owner@example.com');
        $this->addToCart($owner, $this->makeProduct());
        $this->actingAs($owner)->post('/checkout', $this->shipping(['payment_method' => 'bkash', 'transaction_id' => 'ABC123456']));
        $order = Order::firstOrFail();

        $stranger = $this->makeUser('stranger@example.com');
        $this->actingAs($stranger)->get("/orders/{$order->id}")->assertForbidden();
        $this->actingAs($stranger)->get("/orders/{$order->id}/invoice")->assertForbidden();
        $this->actingAs($stranger)->post("/orders/{$order->id}/cancel")->assertForbidden();
    }

    public function test_a_customer_cannot_reach_the_admin_panel(): void
    {
        $customer = $this->makeUser();

        foreach (['/admin', '/admin/orders', '/admin/admins', '/admin/products', '/admin/customers'] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
        $this->actingAs($customer)->post('/admin/admins', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123'])
            ->assertForbidden();
        $this->assertFalse($customer->fresh()->isAdmin());
    }

    public function test_reset_codes_are_limited_to_five_an_hour_per_email(): void
    {
        Mail::fake();
        $this->makeUser();

        for ($i = 0; $i < 8; $i++) {
            \Illuminate\Support\Facades\Cache::forget('pwreset:sent:'.sha1('buyer@example.com'));
            $this->post('/forgot-password', ['email' => 'buyer@example.com']);
        }

        Mail::assertSent(\App\Mail\PasswordCodeMail::class, 5);
    }

    public function test_login_is_throttled_per_address(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $response = $this->post('/login', ['email' => "nobody{$i}@example.com", 'password' => 'wrong-password']);
        }

        // The shop turns a 429 into a friendly redirect with a message.
        $response->assertSessionHas('error', 'Too many attempts. Please wait a moment and try again.');
    }

    public function test_no_demo_or_fake_payment_routes_exist(): void
    {
        $user = $this->makeUser();

        foreach (['/auth/demo', '/auth/demo-admin', '/demo-auto-reset', '/payment/1', '/payment/1/success', '/admin/demo-reset', '/admin/demo-baseline'] as $url) {
            // 404 (no such page) or 405 (the catch-all 404 page only answers GET): either way nothing runs.
            $this->assertContains($this->actingAs($user)->post($url)->getStatusCode(), [404, 405], $url);
        }
        $this->actingAs($user)->get('/payment/1')->assertNotFound();

        $this->assertContains($this->actingAs($this->makeAdmin())->post('/admin/orders/1/courier', ['courier' => 'pathao'])->getStatusCode(), [404, 405]);
    }
}
