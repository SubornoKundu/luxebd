<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slider;
use App\Services\StorefrontCache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /** Only what a product card shows is loaded for the brand and category (keeps the page data small). */
    private const CARD_RELATIONS = ['brand:id,name,slug', 'category:id,name,slug'];

    /** Long texts a product card never shows: left out of the page data. */
    private const CARD_HIDDEN = ['description', 'specifications', 'shipping_info', 'created_at', 'updated_at'];

    /**
     * The storefront home page: hero slider, brand list, category list,
     * latest products, and featured products.
     *
     * Everything on it is the same for every visitor, so it is cached for a
     * short time (see StorefrontCache) instead of being rebuilt on every hit.
     */
    public function index(): Response
    {
        $data = StorefrontCache::remember('home', fn () => [
            'sliders' => Slider::active()->orderBy('sort_order')->get(),
            'brands' => Brand::withCount(['products' => fn ($q) => $q->active()])->orderBy('name')->get(),
            // Her / him / everyone: one row of perfumes for each.
            'genderSections' => $this->genderSections(),
            // Premium (at or above shop.premium_from) and everyday (below it) perfumes.
            'premiumFrom' => (int) config('shop.premium_from'),
            'premiumProducts' => $this->priceTier('>='),
            'affordableProducts' => $this->priceTier('<'),
            'categories' => Category::withCount('products')->whereNull('parent_id')->orderBy('name')->get(),
            // Not shown on the home page itself; kept small (2 each) for anything that still reads them.
            'latestProducts' => $this->lean(Product::with(self::CARD_RELATIONS)
                ->active()
                ->latest()
                ->orderByDesc('id')
                ->take(2)
                ->get()),
            'popularProducts' => $this->popularProducts(),
            'featuredProducts' => $this->lean(Product::with(self::CARD_RELATIONS)
                ->active()
                ->featured()
                ->latest()
                ->orderByDesc('id')
                ->take(2)
                ->get()),
        ]);

        // The first banner picture is announced in the page head (see app.blade.php) so the browser starts
        // downloading it straight away instead of waiting for the page scripts to run.
        $first = collect($data['sliders'] ?? [])->first();
        $image = is_object($first) ? (string) ($first->image ?? '') : '';
        if ($image !== '' && preg_match('#^(/[^/\\]|https?://)#', $image)) {
            request()->attributes->set('preload_image', $image);

            if (preg_match('#^/images/[^?\#]+\.(jpe?g|png|webp)$#i', $image)) {
                request()->attributes->set('preload_srcset', "/thumbs/800{$image} 800w, /thumbs/1200{$image} 1200w, {$image} 1800w");
            }
        }

        return Inertia::render('Home', $data);
    }

    /**
     * The "For Her", "For Him" and "Unisex" categories with their newest perfumes (empty ones are skipped).
     *
     * @return list<array{name: string, slug: string, products: \Illuminate\Support\Collection<int, Product>}>
     */
    protected function genderSections(): array
    {
        $sections = [];

        foreach (['for-her', 'for-him', 'unisex'] as $slug) {
            $category = Category::where('slug', $slug)->first();

            if (! $category) {
                continue;
            }

            $products = Product::with(self::CARD_RELATIONS)
                ->active()
                ->whereIn('category_id', $category->withDescendantIds())
                ->latest()
                ->orderByDesc('id')
                ->take(5)
                ->get();

            $this->lean($products);

            if ($products->isNotEmpty()) {
                $sections[] = ['name' => $category->name, 'slug' => $slug, 'products' => $products];
            }
        }

        return $sections;
    }

    /**
     * Five perfumes from one side of the premium price line ('>=' premium, '<' everyday).
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    protected function priceTier(string $operator)
    {
        $line = (float) config('shop.premium_from');
        $price = 'COALESCE(discount_price, price)';

        return $this->lean(Product::with(self::CARD_RELATIONS)
            ->active()
            ->whereRaw("{$price} {$operator} CAST(? AS DECIMAL(10,2))", [$line])
            ->orderByRaw($operator === '>=' ? "{$price} desc" : 'id desc')
            ->orderByDesc('id')
            ->take(5)
            ->get());
    }

    /**
     * Leaves the long texts out of the products sent to the page (a card never shows them).
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return \Illuminate\Support\Collection<int, Product>
     */
    protected function lean($products)
    {
        $products->each(fn (Product $product) => $product->makeHidden(self::CARD_HIDDEN));

        return $products;
    }

    /**
     * The 8 best sellers that are still on sale, best first.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    protected function popularProducts()
    {
        // A little more than 8 ids, in case some of the top sellers have been hidden since.
        $ids = array_slice(StorefrontCache::popularProductIds(), 0, 60);

        if ($ids === []) {
            return collect();
        }

        $rank = array_flip($ids);

        return $this->lean(Product::with(self::CARD_RELATIONS)
            ->active()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $p) => $rank[$p->id])
            ->take(8)
            ->values());
    }
}
