<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Recently viewed" never shows an old copy of a product (old picture, price, hidden product). */
class RecentlyViewedTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_it_returns_the_current_picture_price_and_stock_in_the_order_asked(): void
    {
        $a = $this->makeProduct(['name' => 'Alpha', 'image' => '/images/vireluxe/products/rayhaan-lion.jpg']);
        $b = $this->makeProduct(['name' => 'Beta', 'price' => 500, 'discount_price' => 400, 'stock' => 3]);

        // the admin replaces Alpha's picture after a customer viewed it
        $a->update(['image' => '/images/uploads/products/new-picture.jpg', 'price' => 1234]);

        $json = $this->getJson("/recently-viewed?ids={$b->id},{$a->id}")->assertOk()->json();

        $this->assertSame(['Beta', 'Alpha'], array_column($json, 'name'));
        $this->assertSame('/images/uploads/products/new-picture.jpg', $json[1]['image']);
        $this->assertEquals(1234, $json[1]['price']);
        $this->assertEquals(400, $json[0]['discount_price']);
        $this->assertSame(3, $json[0]['stock']);
        $this->assertSame(['Acme'], [$json[0]['brand']['name']]);
        $this->assertArrayHasKey('variants_count', $json[0]);
    }

    public function test_hidden_deleted_and_nonsense_ids_are_left_out(): void
    {
        $shown = $this->makeProduct();
        $hidden = $this->makeProduct(['is_active' => false]);
        $gone = $this->makeProduct();
        $goneId = $gone->id;
        $gone->delete();

        $json = $this->getJson("/recently-viewed?ids={$hidden->id},{$goneId},{$shown->id},abc,-5,0,99999")->assertOk()->json();

        $this->assertSame([$shown->id], array_column($json, 'id'));
        $this->getJson('/recently-viewed')->assertOk()->assertExactJson([]);
        $this->getJson('/recently-viewed?ids=')->assertOk()->assertExactJson([]);
    }

    public function test_it_answers_at_most_twelve_products_and_needs_no_login(): void
    {
        $ids = [];
        for ($i = 0; $i < 15; $i++) {
            $ids[] = $this->makeProduct()->id;
        }

        $this->assertCount(12, $this->getJson('/recently-viewed?ids='.implode(',', $ids))->assertOk()->json());
    }

    public function test_the_products_page_url_still_works_next_to_it(): void
    {
        $p = $this->makeProduct();

        $this->get("/products/{$p->slug}")->assertOk();
        $this->get('/products')->assertOk();
    }
}
