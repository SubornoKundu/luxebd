<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slider;
use App\Services\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * Starter data for the Vireluxe perfume shop (several brands, for her / for him / unisex, premium and everyday prices) (php artisan db:seed --class=VireluxeSeeder).
 *
 * It creates NO fake customers, orders, reviews or
 * questions. The perfumes come from the client's photos; their prices and the him/her/unisex split are placeholders: set
 * the real ones from Admin > Products. Safe to run twice.
 */
class VireluxeSeeder extends Seeder
{
    /** [slug, name]: who the perfume is for. Gift sets sit beside them. */
    public const CATEGORIES = [
        ['for-her', 'For Her'],
        ['for-him', 'For Him'],
        ['unisex', 'Unisex'],
        ['gift-sets', 'Gift Sets'],
    ];

    /** The brands of the photos the client sent. [slug, name] */
    public const BRANDS = [
        ['dior', 'Dior'],
        ['davidoff', 'Davidoff'],
        ['armaf', 'Armaf'],
        ['paco-rabanne', 'Paco Rabanne'],
        ['rayhaan', 'Rayhaan'],
        ['afnan', 'Afnan'],
        ['lattafa', 'Lattafa'],
        ['sanaya', 'Sanaya'],
    ];

    /**
     * The perfumes from the client's photos. PRICES ARE PLACEHOLDERS (price for the 30 ml bottle):
     * set the real ones in Admin > Products. Which section a perfume sits in (him / her / unisex)
     * is a first guess too.
     * [slug, name, category slug, brand slug, price for the 30 ml bottle, sale price|null, featured]
     *
     * @var list<array<int, mixed>>
     */
    public const PRODUCTS = [
        ['sauvage-parfum', 'Sauvage Parfum', 'for-him', 'dior', 5500, null, true],
        ['one-million-parfum', '1 Million Parfum', 'for-him', 'paco-rabanne', 5800, null, true],
        ['club-de-nuit-intense-man', 'Club de Nuit Intense Man', 'for-him', 'armaf', 2800, null, true],
        ['cool-water', 'Cool Water', 'for-him', 'davidoff', 2400, null, false],
        ['rayhaan-now', 'Now Eau de Parfum', 'unisex', 'rayhaan', 2200, null, true],
        ['rayhaan-lion', 'Rayhaan Lion', 'for-him', 'rayhaan', 2500, null, false],
        ['afnan-9am-dive', '9am Dive', 'unisex', 'afnan', 2600, null, true],
        ['art-of-universe', 'Art of Universe', 'unisex', 'lattafa', 1900, null, false],
        ['sanaya-40', 'Sanaya 40', 'unisex', 'sanaya', 1600, null, false],
    ];

    /** Extra photos of a perfume (the main one is {slug}.jpg). */
    public const GALLERY = [
        'sauvage-parfum' => ['sauvage-parfum-2'],
        'cool-water' => ['cool-water-2'],
    ];

    /** Bottle sizes: [label, share of the 30 ml price added on top, stock]. The gift set has one fixed pack. */
    public const SIZES = [
        ['30 ml', 0.0, 20],
        ['50 ml', 0.4, 20],
        ['100 ml', 1.0, 12],
    ];

    public function run(): void
    {
        $this->settings();

        $brands = [];
        foreach (self::BRANDS as [$slug, $name]) {
            $brands[$slug] = Brand::updateOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        foreach (self::CATEGORIES as [$slug, $name]) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'image' => "/images/vireluxe/categories/{$slug}.jpg"]);
        }

        $categories = Category::pluck('id', 'slug');

        foreach (self::PRODUCTS as [$slug, $name, $category, $brandSlug, $price, $sale, $featured]) {
            $product = Product::updateOrCreate(['slug' => $slug], [
                'category_id' => $categories[$category],
                'brand_id' => $brands[$brandSlug],
                'name' => $name,
                'description' => "Original {$name} by ".Brand::find($brands[$brandSlug])->name.'. Available in 30, 50 and 100 ml.',
                'specifications' => [['label' => 'Brand', 'value' => Brand::find($brands[$brandSlug])->name]],
                'price' => $price,
                'discount_price' => $sale,
                'image' => "/images/vireluxe/products/{$slug}.jpg",
                'stock' => 20,
                'is_featured' => $featured,
                'is_active' => true,
            ]);

            foreach (self::GALLERY[$slug] ?? [] as $i => $extra) {
                $product->images()->firstOrCreate(['path' => "/images/vireluxe/products/{$extra}.jpg"], ['sort_order' => $i + 1]);
            }

            // Perfumes come in sizes; the gift set is one fixed pack.
            if ($category !== 'gift-sets' && $product->variants()->count() === 0) {
                foreach (self::SIZES as $i => [$label, $share, $stock]) {
                    $extra = (int) (round($price * $share / 50) * 50);
                    $product->variants()->create(['type' => $label, 'extra_price' => $extra, 'stock' => $stock, 'sort_order' => $i]);
                }
                $product->syncStockFromVariants();
            }
        }

        // Home page banners: any old placeholder banners go, Vireluxe's two wide banners take their place.
        // Later changes (add, replace, reorder, hide) are made in Admin > Sliders.
        Slider::query()->where('image', 'like', '/images/sliders/%')->delete();

        foreach ([
            ['Vireluxe Fragrance - Shop now', '/images/vireluxe/banners/banner-fragrance.jpg', 1],
            ['Luxury Perfume - Shop now', '/images/vireluxe/banners/banner-luxury.jpg', 2],
        ] as [$title, $image, $order]) {
            Slider::updateOrCreate(
                ['image' => $image],
                ['title' => $title, 'subtitle' => null, 'link' => '/products', 'sort_order' => $order, 'is_active' => true],
            );
        }
    }

    /** Shop name, tagline and the details the owner gave us. Existing saved values are kept. */
    protected function settings(): void
    {
        $existing = SiteSettings::stored();

        $values = [
            'site_name' => 'Vireluxe',
            'tagline' => 'Signature fragrances, made for Bangladesh. Order on the site, pay by bKash, and we deliver to your door.',
            'meta_description' => 'Vireluxe: signature perfumes for men, women and everyone. Pay by bKash and get delivery across Bangladesh.',
            'address' => 'Uttor Char Mojlishpur (Plus Code W8PP+3GR)',
            'bkash_number' => '01860291022',
            'free_shipping_over' => '6000',
        ];

        SiteSettings::save(array_filter(
            $values,
            fn ($value, $key) => ! array_key_exists($key, $existing) || $existing[$key] === null || $existing[$key] === '',
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
