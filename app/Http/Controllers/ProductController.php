<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StorefrontCache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Fresh details for the "Recently viewed" strip. The browser only remembers product ids;
     * name, picture, price and stock are read here every time, so a changed or deleted
     * picture, a new price or a hidden product is never shown from an old copy.
     */
    public function recent(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(12)
            ->values();

        $products = Product::query()->with('brand:id,name')->active()->whereIn('id', $ids)->get()->keyBy('id');

        return response()->json($ids->map(fn ($id) => $products->get($id))->filter()->values()->map(fn (Product $p) => [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'image' => $p->image,
            'price' => $p->price,
            'discount_price' => $p->discount_price,
            'stock' => $p->stock,
            'variants_count' => (int) ($p->variants_count ?? 0),
            'brand' => $p->brand ? ['name' => $p->brand->name] : null,
        ]));
    }

    /**
     * Product listing, filterable by brand, category, and a simple
     * name search -- e.g. /products?category=electronics&brand=apple.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'brand' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:latest,popular,price_asc,price_desc,name'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->with(['brand', 'category'])
            ->active()
            ->when($filters['brand'] ?? null, fn ($query, $slug) => $query->whereHas(
                'brand',
                fn ($brand) => $brand->where('slug', $slug),
            ))
            ->when($filters['category'] ?? null, function ($query, $slug) {
                // A parent category also lists the products of its sub-categories.
                $ids = Category::where('slug', $slug)->first()?->withDescendantIds() ?? [0];

                $query->whereIn('category_id', $ids);
            })
            ->when($filters['q'] ?? null, function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(fn ($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->when(isset($filters['min_price']), fn ($query) => $query->whereRaw(
                'COALESCE(discount_price, price) >= CAST(? AS DECIMAL(10,2))', [(float) $filters['min_price']],
            ))
            ->when(isset($filters['max_price']), fn ($query) => $query->whereRaw(
                'COALESCE(discount_price, price) <= CAST(? AS DECIMAL(10,2))', [(float) $filters['max_price']],
            ))
            ->when($request->boolean('in_stock'), fn ($query) => $query->where('stock', '>', 0))
            ->when(($filters['sort'] ?? null) === 'popular', function ($query) {
                // Best sellers come first, ranked from one cached grouped query (see
                // StorefrontCache) instead of a sub-query per product; the rest follow newest first.
                $ids = StorefrontCache::popularProductIds();

                if ($ids !== []) {
                    $case = 'CASE products.id';
                    foreach ($ids as $rank => $id) {
                        $case .= ' WHEN '.(int) $id.' THEN '.(int) $rank;
                    }
                    $query->orderByRaw($case.' ELSE '.count($ids).' END');
                }

                $query->orderByDesc('products.id');
            })
            ->when(
                ($filters['sort'] ?? null) === 'price_asc',
                fn ($query) => $query->orderByRaw('COALESCE(discount_price, price) asc')->orderByDesc('id'),
            )
            ->when(
                ($filters['sort'] ?? null) === 'price_desc',
                fn ($query) => $query->orderByRaw('COALESCE(discount_price, price) desc')->orderByDesc('id'),
            )
            ->when(
                ($filters['sort'] ?? null) === 'name',
                fn ($query) => $query->orderBy('name')->orderByDesc('id'),
            )
            ->when(
                ! isset($filters['sort']) || $filters['sort'] === 'latest',
                fn ($query) => $query->latest()->orderByDesc('id'),
            )
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'brands' => Brand::withCount(['products' => fn ($q) => $q->active()])->orderBy('name')->get(['id', 'name', 'slug']),
            'categories' => Category::orderBy('name')->get(['id', 'parent_id', 'name', 'slug']),
            'filters' => [
                'brand' => $filters['brand'] ?? null,
                'category' => $filters['category'] ?? null,
                'q' => $filters['q'] ?? null,
                'sort' => $filters['sort'] ?? 'latest',
                'min_price' => $filters['min_price'] ?? null,
                'max_price' => $filters['max_price'] ?? null,
                'in_stock' => $request->boolean('in_stock') ? 1 : null,
            ],
        ]);
    }

    /**
     * A single product's detail page, plus a small "related products"
     * rail from the same category.
     */
    public function show(Request $request, Product $product): Response
    {
        abort_unless($product->is_active, 404);

        $product->load(['brand', 'category.parent', 'variants', 'images']);

        $related = Product::with(['brand', 'category'])
            ->active()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->take(4)
            ->get();

        $user = $request->user();

        $this->seo($product->name, $product->description ?: ($product->brand?->name.' '.$product->name), $product->image, 'product');

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $related,
            'bundle' => $this->bundleFor($product),
            'questions' => $product->questions()->where('is_published', true)->with('user:id,name')->latest()->take(30)->get(),
            'shippingInfo' => $product->shipping_info ?: config('shop.default_shipping_info'),
            'lowStockThreshold' => (int) config('shop.low_stock_threshold'),
            'reviews' => $product->approvedReviews()->with('user:id,name')->latest()->take(30)->get(),
            'ratingAvg' => round((float) $product->approvedReviews()->avg('rating'), 1),
            'ratingCount' => $product->approvedReviews()->count(),
            'canReview' => $user ? $this->hasPurchased($user->id, $product->id) : false,
            'myReview' => $user ? $product->reviews()->where('user_id', $user->id)->first() : null,
        ]);
    }

    /**
     * "Frequently bought together": other products that showed up in the same
     * orders, topped up with in-stock products from the same category.
     * Products that need a size/colour pick are left out so the bundle can be
     * added in one tap.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    protected function bundleFor(Product $product)
    {
        $base = fn () => Product::with(['brand', 'category'])
            ->active()
            ->where('stock', '>', 0)
            ->whereDoesntHave('variants')
            ->whereKeyNot($product->id);

        $together = $base()
            ->whereIn('id', OrderItem::whereIn(
                'order_id',
                OrderItem::where('product_id', $product->id)->select('order_id'),
            )->where('product_id', '!=', $product->id)->select('product_id'))
            ->take(2)
            ->get();

        if ($together->count() < 2) {
            $together = $together->merge(
                $base()
                    ->where('category_id', $product->category_id)
                    ->whereNotIn('id', $together->pluck('id'))
                    ->orderByDesc('is_featured')
                    ->latest('id')
                    ->take(2 - $together->count())
                    ->get()
            );
        }

        return $together->values();
    }

    /**
     * Only customers whose order containing this product was delivered may
     * review it ("verified purchase").
     */
    public static function hasPurchased(int $userId, int $productId): bool
    {
        return \App\Models\OrderItem::where('product_id', $productId)
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('status', 'completed'))
            ->exists();
    }
}
