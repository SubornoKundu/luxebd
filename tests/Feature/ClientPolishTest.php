<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Error pages, help pages, order tracking, guest hint and share tags. */
class ClientPolishTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_unknown_address_shows_the_branded_404_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $p) => $p->component('Error', false)->where('status', 404));

        $this->get('/products/no-such-product')
            ->assertNotFound()
            ->assertInertia(fn (Assert $p) => $p->component('Error', false)->where('status', 404));
    }

    public function test_customer_opening_the_admin_area_gets_the_branded_403_page(): void
    {
        $this->actingAs($this->makeUser())->get('/admin')
            ->assertForbidden()
            ->assertInertia(fn (Assert $p) => $p->component('Error', false)->where('status', 403));
    }

    public function test_server_errors_are_branded_when_debug_is_off_and_json_stays_json(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_boom', fn () => throw new \RuntimeException('secret detail'));

        $this->get('/_boom')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $p) => $p->component('Error', false)->where('status', 500));

        $this->getJson('/_boom')->assertStatus(500)->assertJsonMissing(['message' => 'secret detail']);
    }

    public function test_every_help_page_opens_and_uses_the_shop_settings(): void
    {
        config(['shop.contact.phone' => '+880 1711-222333']);

        foreach (array_keys(PageController::PAGES) as $slug) {
            $this->get('/'.$slug)->assertOk()->assertInertia(fn (Assert $p) => $p
                ->component('Info/Show', false)
                ->where('slug', $slug)
                ->has('sections')
                ->where('shop.contact.phone', '+880 1711-222333'));
        }

        $this->get('/returns')->assertInertia(fn (Assert $p) => $p->where('title', 'Returns & refunds'));
    }

    public function test_track_order_needs_the_right_phone_and_hides_the_address(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct(['name' => 'Tracked Phone']));
        $order = app(OrderService::class)->place($user, $this->shipping(['shipping_phone' => '01711111111']), 'cod');

        // any common way of writing the same number
        foreach (['01711111111', '+8801711111111', '8801711111111', '0171-1111111'] as $phone) {
            $this->get('/track-order?'.http_build_query(['order' => strtolower($order->order_number), 'phone' => $phone]))
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p
                    ->component('TrackOrder', false)
                    ->where('result.order_number', $order->order_number)
                    ->has('result.steps')
                    ->where('result.items.0.name', 'Tracked Phone')
                    ->missing('result.shipping_address')
                    ->missing('result.shipping_phone'));
        }

        // wrong phone and unknown order give the same "not found" answer
        foreach ([['order' => $order->order_number, 'phone' => '01799999999'], ['order' => 'ORD-NOPE', 'phone' => '01711111111']] as $q) {
            $this->get('/track-order?'.http_build_query($q))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where('searched', true)->where('result', null));
        }

        // the empty form
        $this->get('/track-order')->assertOk()->assertInertia(fn (Assert $p) => $p->where('searched', false)->where('result', null));
    }

    public function test_guests_are_told_why_they_land_on_the_login_page(): void
    {
        $product = $this->makeProduct();

        $this->from('/products/'.$product->slug)->post('/cart', ['product_id' => $product->id])
            ->assertRedirect('/login');
        $this->assertSame('Please sign in to add items to your cart.', session('login_notice'));

        $this->get('/login')->assertInertia(fn (Assert $p) => $p
            ->component('Auth/Login', false)->where('notice', 'Please sign in to add items to your cart.'));

        // the hint is shown once
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->where('notice', null));
    }

    public function test_share_tags_show_the_product_name_picture_and_a_canonical_link(): void
    {
        $product = $this->makeProduct(['name' => 'Shareable Phone', 'description' => 'A very <b>nice</b> phone']);

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('property="og:title" content="Shareable Phone"', $html);
        $this->assertStringContainsString('property="og:type" content="product"', $html);
        $this->assertStringContainsString('property="og:image" content="'.url($product->image).'"', $html);
        $this->assertStringContainsString('A very nice phone', $html);
        $this->assertStringContainsString('rel="canonical"', $html);

        // pages without their own details still get site-level tags
        $this->assertStringContainsString('property="og:site_name"', $this->get('/')->getContent());
    }

    public function test_robots_file_keeps_private_areas_out_of_search_engines(): void
    {
        $txt = file_get_contents(public_path('robots.txt'));

        foreach (['/admin', '/cart', '/checkout', '/orders'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $txt);
        }
    }

    public function test_expired_order_payment_helpers_unchanged(): void
    {
        // sanity: placing an order still works end to end with the new locking
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());
        $order = app(OrderService::class)->place($user, $this->shipping(), 'cod');
        $this->assertSame(Order::STATUS_PENDING, $order->status);
    }

    public function test_default_site_icon_is_used_until_one_is_uploaded(): void
    {
        foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('href="/favicon.svg"', $html);
        $this->assertStringContainsString('href="/apple-touch-icon.png"', $html);

        \App\Models\Setting::updateOrCreate(['key' => 'favicon'], ['value' => '/images/uploads/site/custom.png']);
        \App\Services\SiteSettings::applyToConfig();
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<link rel="icon" href="/images/uploads/site/custom.png">', $html);
        $this->assertStringNotContainsString('href="/favicon.svg"', $html);
    }
}
