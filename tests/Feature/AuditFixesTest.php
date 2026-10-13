<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Wishlist;
use App\Services\CheckoutException;
use App\Services\OrderService;
use App\Services\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Regression tests for the full-site audit (edge cases a client could hit). */
class AuditFixesTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_first_address_is_always_default_and_later_default_request_wins(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $row = ['label' => 'Home', 'name' => 'Rakib', 'phone' => '01711111111', 'address' => 'Road 1', 'city' => 'Dhaka'];

        $r = $this->post('/account/addresses', $row + ['is_default' => 0]);
        $r->assertSessionHasNoErrors();
        $r->assertSessionHas('success');
        $this->assertTrue((bool) Address::first()->is_default);

        $this->post('/account/addresses', ['label' => 'Office'] + $row + ['is_default' => 1]);
        $this->assertSame('Office', Address::where('is_default', true)->sole()->label);
    }

    public function test_inactive_products_are_hidden_from_the_wishlist_and_count(): void
    {
        $user = $this->makeUser();
        $live = $this->makeProduct();
        $gone = $this->makeProduct();
        Wishlist::create(['user_id' => $user->id, 'product_id' => $live->id]);
        Wishlist::create(['user_id' => $user->id, 'product_id' => $gone->id]);
        $gone->update(['is_active' => false]);

        $this->actingAs($user)->get('/wishlist')->assertInertia(fn ($p) => $p->has('wishlists', 1));
        $this->get('/')->assertInertia(fn ($p) => $p->where('wishlistCount', 1));

        $this->post('/wishlist', ['product_id' => $gone->id])->assertSessionHas('error');
        $this->post('/cart', ['product_id' => $gone->id])->assertSessionHas('error');
    }

    public function test_buy_now_sets_the_quantity_instead_of_adding(): void
    {
        $user = $this->makeUser();
        $p = $this->makeProduct(['stock' => 20]);
        $this->addToCart($user, $p, 3);

        $this->actingAs($user)->post('/cart', ['product_id' => $p->id, 'quantity' => 2, 'buy_now' => 1])
            ->assertRedirect(route('checkout.index'));
        $this->assertSame(2, CartItem::where('user_id', $user->id)->sole()->quantity);

        $this->post('/cart', ['product_id' => $p->id, 'quantity' => 2]);
        $this->assertSame(4, CartItem::where('user_id', $user->id)->sole()->quantity);
    }

    public function test_product_search_matches_brand_and_category_names(): void
    {
        $this->makeProduct(['name' => 'Plain Thing']); // brand "Acme", category "Gadgets"

        $this->get('/products?q=acme')->assertInertia(fn ($p) => $p->has('products.data', 1));
        $this->get('/products?q=gadget')->assertInertia(fn ($p) => $p->has('products.data', 1));
        $this->get('/products?q=zzzz')->assertInertia(fn ($p) => $p->has('products.data', 0));
    }

    public function test_pagination_has_no_duplicates_across_pages_with_equal_prices(): void
    {
        foreach (range(1, 30) as $i) {
            $this->makeProduct(['name' => 'Same', 'price' => 500]);
        }

        foreach (['latest', 'price_asc', 'price_desc', 'name'] as $sort) {
            $ids = [];
            foreach ([1, 2, 3] as $page) {
                $res = $this->get("/products?sort={$sort}&page={$page}");
                $ids = array_merge($ids, collect($res->inertiaProps('products.data'))->pluck('id')->all());
            }
            $this->assertCount(30, array_unique($ids), "duplicates/missing with sort {$sort}");
        }
    }

    public function test_second_checkout_with_an_empty_cart_is_refused_not_duplicated(): void
    {
        $user = $this->makeUser();
        $this->addToCart($user, $this->makeProduct());
        $service = app(OrderService::class);

        $service->place($user, $this->shipping(), 'cod');
        try {
            $service->place($user, $this->shipping(), 'cod');
            $this->fail('second order should not be created');
        } catch (CheckoutException) {
        }
        $this->assertSame(1, Order::count());
    }

    public function test_importer_keeps_existing_values_for_blank_optional_cells(): void
    {
        $p = $this->makeProduct(['name' => 'Keep Me', 'slug' => 'keep-me', 'description' => 'Long text', 'discount_price' => 800, 'is_featured' => true]);

        $csv = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($csv, "name,category,brand,price,stock\nKeep Me,Gadgets,Acme,900,7\n");
        $report = app(ProductImporter::class)->import($csv);

        $this->assertSame(1, $report['updated']);
        $p->refresh();
        $this->assertSame('Long text', $p->description);
        $this->assertEquals(800, $p->discount_price);
        $this->assertTrue((bool) $p->is_featured);
        $this->assertEquals(900, $p->price);
    }

    public function test_images_used_by_past_orders_are_not_deleted_with_the_product(): void
    {
        $dir = public_path('images/uploads/products');
        @mkdir($dir, 0777, true);
        $file = $dir.'/keepme-test.jpg';
        file_put_contents($file, 'x');

        $p = $this->makeProduct(['image' => '/images/uploads/products/keepme-test.jpg']);
        $order = Order::create([
            'user_id' => $this->makeUser()->id, 'order_number' => 'T-1', 'subtotal' => 1, 'shipping_fee' => 0,
            'discount_amount' => 0, 'total_amount' => 1, 'status' => 'completed', 'payment_status' => 'paid',
            'payment_method' => 'cod', 'shipping_name' => 'a', 'shipping_phone' => '1', 'shipping_address' => 'a', 'shipping_city' => 'a',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'product_name' => 'x',
            'product_image' => '/images/uploads/products/keepme-test.jpg', 'price' => 1, 'quantity' => 1, 'subtotal' => 1]);

        $this->actingAs($this->makeAdmin())->delete("/admin/products/{$p->id}");
        $this->assertFileExists($file);
        @unlink($file);
    }

    public function test_expired_session_gets_a_friendly_redirect_instead_of_an_error_page(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->post('/_t419', fn () => throw new \Illuminate\Session\TokenMismatchException());
        $this->actingAs($this->makeUser());

        $this->from('/cart')->post('/_t419')
            ->assertRedirect('/cart')
            ->assertSessionHas('error');
    }
}
