<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Several pictures per product: admin upload / remove / make main, and the shop page. */
class ProductGalleryTest extends TestCase
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
        foreach ($this->uploaded as $path) {
            @unlink(public_path(ltrim($path, '/')));
        }
        parent::tearDown();
    }

    protected function payload(array $extra = []): array
    {
        $cat = Category::firstOrCreate(['slug' => 'phones'], ['name' => 'Phones']);
        $brand = Brand::firstOrCreate(['slug' => 'zed'], ['name' => 'Zed']);

        return array_merge([
            'name' => 'Gallery Phone', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'description' => 'Nice',
            'price' => 5000, 'stock' => 5, 'is_active' => true,
        ], $extra);
    }

    protected function pics(int $n): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("g{$i}.jpg", 300, 300), range(1, $n));
    }

    protected function track(Product $product): void
    {
        $this->uploaded[] = $product->image;
        foreach ($product->images as $extra) {
            $this->uploaded[] = $extra->path;
        }
    }

    public function test_admin_can_create_a_product_with_many_pictures(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('main.jpg', 400, 400), 'gallery' => $this->pics(3)]))
            ->assertRedirect('/admin/products');

        $product = Product::where('name', 'Gallery Phone')->with('images')->firstOrFail();
        $this->track($product);

        $this->assertCount(3, $product->images);
        $this->assertSame([1, 2, 3], $product->images->pluck('sort_order')->all());
        foreach ($product->images as $extra) {
            $this->assertStringStartsWith('/images/uploads/products/', $extra->path);
            $this->assertFileExists(public_path(ltrim($extra->path, '/')));
        }
        $this->assertNotContains($product->image, $product->images->pluck('path')->all());
    }

    public function test_pictures_can_be_added_and_removed_later_and_the_main_picture_is_kept(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('main.jpg'), 'gallery' => $this->pics(2)]));
        $product = Product::where('name', 'Gallery Phone')->with('images')->firstOrFail();
        $this->track($product);
        $main = $product->image;
        [$first, $second] = $product->images->all();

        $this->put("/admin/products/{$product->id}", $this->payload([
            'remove_gallery' => [$first->id], 'gallery' => $this->pics(2),
        ]))->assertRedirect('/admin/products');

        $product->refresh()->load('images');
        $this->track($product);
        $this->assertSame($main, $product->image);
        $this->assertCount(3, $product->images);
        $this->assertNull(ProductImage::find($first->id));
        $this->assertFileDoesNotExist(public_path(ltrim($first->path, '/')));
        $this->assertFileExists(public_path(ltrim($second->path, '/')));
        $this->assertSame([$second->id], $product->images->take(1)->pluck('id')->all(), 'old pictures keep their order, new ones go last');
    }

    public function test_make_main_swaps_without_losing_anything(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('main.jpg'), 'gallery' => $this->pics(2)]));
        $product = Product::where('name', 'Gallery Phone')->with('images')->firstOrFail();
        $this->track($product);
        $oldMain = $product->image;
        $extra = $product->images->first();
        $extraPath = $extra->path;

        $this->put("/admin/products/{$product->id}", $this->payload(['main_gallery_id' => $extra->id]))->assertRedirect('/admin/products');

        $product->refresh();
        $this->assertSame($extraPath, $product->image);
        $this->assertSame($oldMain, $extra->fresh()->path);
        $this->assertFileExists(public_path(ltrim($oldMain, '/')));
        $this->assertFileExists(public_path(ltrim($extraPath, '/')));
        $this->assertSame(2, $product->images()->count());

        // another product's picture id cannot be used
        $other = $this->makeProduct();
        $foreign = ProductImage::create(['product_id' => $other->id, 'path' => '/images/vireluxe/products/rayhaan-lion.jpg', 'sort_order' => 1]);
        $this->put("/admin/products/{$product->id}", $this->payload(['main_gallery_id' => $foreign->id]));
        $this->assertSame($extraPath, $product->fresh()->image);
        $this->assertSame('/images/vireluxe/products/rayhaan-lion.jpg', $foreign->fresh()->path);
    }

    public function test_a_new_main_picture_wins_over_make_main(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('main.jpg'), 'gallery' => $this->pics(1)]));
        $product = Product::where('name', 'Gallery Phone')->with('images')->firstOrFail();
        $extra = $product->images->first();

        $this->put("/admin/products/{$product->id}", $this->payload(['image' => UploadedFile::fake()->image('new.jpg'), 'main_gallery_id' => $extra->id]));
        $product->refresh()->load('images');
        $this->track($product);

        $this->assertNotSame($extra->path, $product->image);
        $this->assertSame($extra->path, $product->images->first()->path);
    }

    public function test_limit_and_file_type_are_enforced_before_anything_is_saved(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('m.jpg'), 'gallery' => $this->pics(11)]))
            ->assertSessionHasErrors('gallery');
        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('m.jpg'), 'gallery' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')]]))
            ->assertSessionHasErrors('gallery.0');
        $this->assertSame(0, Product::count());

        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('m.jpg'), 'gallery' => $this->pics(8)]));
        $product = Product::where('name', 'Gallery Phone')->with('images')->firstOrFail();
        $this->track($product);

        $this->put("/admin/products/{$product->id}", $this->payload(['gallery' => $this->pics(3)]))->assertSessionHasErrors('gallery');
        $this->assertSame(8, $product->images()->count());

        // removing some in the same save makes room
        $ids = $product->images->take(3)->pluck('id')->all();
        $this->put("/admin/products/{$product->id}", $this->payload(['gallery' => $this->pics(3), 'remove_gallery' => $ids]))->assertRedirect('/admin/products');
        $product->refresh()->load('images');
        $this->track($product);
        $this->assertSame(8, $product->images->count());
    }

    public function test_deleting_a_product_removes_its_pictures_but_keeps_ones_used_by_past_orders(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/products', $this->payload(['image' => UploadedFile::fake()->image('m.jpg'), 'gallery' => $this->pics(2)]));
        $product = Product::where('name', 'Gallery Phone')->with('images')->firstOrFail();
        [$a, $b] = $product->images->all();
        $this->track($product);

        $order = \App\Models\Order::create([
            'user_id' => $this->makeUser()->id, 'order_number' => 'GAL-1', 'subtotal' => 1, 'shipping_fee' => 0, 'discount_amount' => 0,
            'total_amount' => 1, 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'shipping_name' => 'a', 'shipping_phone' => '01711111111', 'shipping_address' => 'x', 'shipping_city' => 'Dhaka',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Gallery Phone', 'product_image' => $a->path, 'price' => 1, 'quantity' => 1, 'subtotal' => 1]);

        $this->delete("/admin/products/{$product->id}")->assertRedirect();

        $this->assertNull(Product::find($product->id));
        $this->assertSame(0, ProductImage::where('product_id', $product->id)->count());
        $this->assertFileExists(public_path(ltrim($a->path, '/')), 'a picture shown on a past order stays');
        $this->assertFileDoesNotExist(public_path(ltrim($b->path, '/')));
    }

    public function test_the_edit_form_and_the_product_page_receive_the_pictures(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(['name' => 'Gallery Page Phone']);
        foreach (['/images/products/a.jpg', '/images/products/b.jpg'] as $i => $path) {
            ProductImage::create(['product_id' => $product->id, 'path' => $path, 'sort_order' => $i + 1]);
        }

        $this->actingAs($admin)->get("/admin/products/{$product->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Products/Form', false)->has('product.images', 2)->where('product.images.0.path', '/images/products/a.jpg'));

        $this->get("/products/{$product->slug}")
            ->assertInertia(fn (Assert $p) => $p->component('Products/Show', false)->has('product.images', 2)->where('product.images.1.path', '/images/products/b.jpg'));
    }

    public function test_product_lists_do_not_load_the_gallery(): void
    {
        $product = $this->makeProduct();
        ProductImage::create(['product_id' => $product->id, 'path' => '/images/products/a.jpg', 'sort_order' => 1]);

        $this->get('/products')->assertInertia(fn (Assert $p) => $p->missing('products.data.0.images'));
    }
}
