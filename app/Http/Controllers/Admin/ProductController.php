<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use HandlesImageUploads;

    /** Extra pictures allowed per product, on top of the main one. */
    public const MAX_GALLERY = 10;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive,low,out'],
        ]);

        $products = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['brand'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'low', fn ($q) => $q->where('stock', '>', 0)->where('stock', '<=', config('shop.low_stock_threshold')))
            ->when(($filters['status'] ?? null) === 'out', fn ($q) => $q->where('stock', '<=', 0))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? '',
                'brand' => $filters['brand'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'lowStockThreshold' => (int) config('shop.low_stock_threshold'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(null));
        $this->assertGalleryRoom($request, null);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image'] = $this->storeImage($request->file('image'), 'products');

        DB::transaction(function () use ($request, $data) {
            $product = Product::create($this->productAttributes($data, $request));
            $this->syncVariants($request, $product);
            $this->syncGallery($request, $product);
        });

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => $product->load('variants', 'images'),
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate($this->rules($product));
        $this->assertGalleryRoom($request, $product);

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $data['image'] = $this->storeImage($request->file('image'), 'products');
        } else {
            unset($data['image']);
        }

        DB::transaction(function () use ($request, $product, $data) {
            $product->update($this->productAttributes($data, $request));
            $this->syncVariants($request, $product);
            $this->syncGallery($request, $product);
        });

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteImage($product->image);
        $product->images->each(fn ($extra) => $this->deleteImage($extra->path));
        $product->delete();

        return back()->with('success', 'Product deleted. Past orders keep their own copy of its details.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?Product $product): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['required', 'exists:brands,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'shipping_info' => ['nullable', 'string', 'max:2000'],
            'specifications' => ['nullable', 'array', 'max:30'],
            'specifications.*.label' => ['nullable', 'string', 'max:80'],
            'specifications.*.value' => ['nullable', 'string', 'max:255'],
            'variants' => ['nullable', 'array', 'max:60'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.size' => ['nullable', 'string', 'max:50'],
            'variants.*.color' => ['nullable', 'string', 'max:50'],
            'variants.*.type' => ['nullable', 'string', 'max:50'],
            'variants.*.extra_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'discount_price' => ['nullable', 'numeric', 'gt:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'image' => [$product ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // extra pictures: new uploads, ids to remove, and an existing one to promote to main
            'gallery' => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['integer'],
            'main_gallery_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Refuse the save (before anything is written) if it would leave the
     * product with more than MAX_GALLERY extra pictures.
     */
    protected function assertGalleryRoom(Request $request, ?Product $product): void
    {
        $new = count((array) $request->file('gallery', []));

        if ($new === 0) {
            return;
        }

        $removed = array_map('intval', (array) $request->input('remove_gallery', []));
        $kept = $product ? $product->images()->whereNotIn('id', $removed ?: [0])->count() : 0;

        if ($kept + $new > self::MAX_GALLERY) {
            throw ValidationException::withMessages([
                'gallery' => 'A product can have at most '.self::MAX_GALLERY.' extra pictures. Remove some first.',
            ]);
        }
    }

    /**
     * Apply the gallery part of the form: remove ticked pictures, promote one
     * to the main picture, then add newly uploaded ones at the end.
     */
    protected function syncGallery(Request $request, Product $product): void
    {
        $removeIds = array_map('intval', (array) $request->input('remove_gallery', []));

        if ($removeIds) {
            $product->images()->whereIn('id', $removeIds)->get()->each(function ($extra) {
                $this->deleteImage($extra->path);
                $extra->delete();
            });
        }

        // "Make main": swap with the current main picture (nothing is lost).
        $mainId = (int) $request->input('main_gallery_id');

        if ($mainId && ! $request->hasFile('image') && ! in_array($mainId, $removeIds, true)) {
            $extra = $product->images()->whereKey($mainId)->first();

            if ($extra) {
                $oldMain = $product->image;
                $product->image = $extra->path;
                $product->save();
                $extra->path = $oldMain;
                $extra->save();
            }
        }

        $next = (int) $product->images()->max('sort_order') + 1;

        foreach ((array) $request->file('gallery', []) as $file) {
            $product->images()->create(['path' => $this->storeImage($file, 'products'), 'sort_order' => $next++]);
        }

        $product->unsetRelation('images');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug(mb_substr($name, 0, 200)) ?: 'product';
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Columns for the products table (drops the form-only extras).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function productAttributes(array $data, Request $request): array
    {
        unset($data['variants'], $data['gallery'], $data['remove_gallery'], $data['main_gallery_id']);

        if ($request->boolean('sync_options')) {
            $specs = collect($data['specifications'] ?? [])
                ->map(fn ($row) => ['label' => trim((string) ($row['label'] ?? '')), 'value' => trim((string) ($row['value'] ?? ''))])
                ->filter(fn ($row) => $row['label'] !== '' && $row['value'] !== '')
                ->values()
                ->all();

            $data['specifications'] = $specs ?: null;
        } else {
            unset($data['specifications']);
        }

        return $data;
    }

    /**
     * Create / update / delete the product's variants to match the form, then
     * make the product's stock the sum of its variants' stock. Only runs when
     * the form says it carries the options section (`sync_options`).
     */
    protected function syncVariants(Request $request, Product $product): void
    {
        if (! $request->boolean('sync_options')) {
            // The typed stock only applies to products without variants.
            $product->syncStockFromVariants();

            return;
        }

        $kept = [];

        foreach (array_values($request->input('variants', [])) as $i => $row) {
            $size = trim((string) ($row['size'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));
            $type = trim((string) ($row['type'] ?? ''));

            if ($size === '' && $color === '' && $type === '') {
                continue;
            }

            $variant = ! empty($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : null;
            $variant ??= new ProductVariant(['product_id' => $product->id]);

            $variant->fill([
                'size' => $size ?: null,
                'color' => $color ?: null,
                'type' => $type ?: null,
                'extra_price' => (float) ($row['extra_price'] ?? 0),
                'stock' => (int) ($row['stock'] ?? 0),
                'sort_order' => count($kept),
            ]);

            if ($file = $request->file("variants.$i.image")) {
                $this->deleteImage($variant->image);
                $variant->image = $this->storeImage($file, 'variants');
            } elseif (filter_var($row['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->deleteImage($variant->image);
                $variant->image = null;
            }

            $variant->save();
            $kept[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $kept)->get()->each(function (ProductVariant $gone) {
            $this->deleteImage($gone->image);
            $gone->delete();
        });

        $product->unsetRelation('variants');
        $product->syncStockFromVariants();
    }

    /**
     * Categories for dropdowns, sub-categories shown as "Parent › Child".
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    protected function categoryOptions()
    {
        return Category::with('parent:id,name')->orderBy('name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->parent ? $c->parent->name.' › '.$c->name : $c->name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Bulk upload: create / update products from a CSV file.
     */
    public function import(Request $request, ProductImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Please choose a CSV file.',
            'file.mimes' => 'The file must be a .csv file.',
        ]);

        $report = $importer->import($request->file('file')->getRealPath());

        $message = "Import finished: {$report['created']} created, {$report['updated']} updated, {$report['skipped']} skipped.";

        if ($report['errors']) {
            $message .= ' '.implode(' ', array_slice($report['errors'], 0, 5))
                .(count($report['errors']) > 5 ? ' (+'.(count($report['errors']) - 5).' more rows had problems)' : '');
        }

        return back()->with($report['created'] + $report['updated'] > 0 ? 'success' : 'error', $message);
    }

    /**
     * A ready-to-fill CSV with the right columns and two sample rows.
     */
    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ProductImporter::COLUMNS, ',', '"', '');
            fputcsv($out, ['Wireless Earbuds X1', 'Electronics', 'Audio', 'Acme', 1999, 1599, 40, 'Compact earbuds with 24h battery.', '', 'yes', 'yes'], ',', '"', '');
            fputcsv($out, ['Cotton Polo Shirt', 'Fashion', '', 'Acme', 899, '', 120, 'Soft everyday polo.', '', 'no', 'yes'], ',', '"', '');
            fclose($out);
        }, \Illuminate\Support\Str::slug(config('app.name')).'-products-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
