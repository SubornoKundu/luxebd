<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Instant-search suggestions for the header search box.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['products' => [], 'categories' => [], 'total' => 0]);
        }

        $like = '%'.addcslashes(mb_substr($term, 0, 80), '%_\\').'%';

        $query = Product::query()
            ->active()
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)));

        $total = (clone $query)->count();

        $products = $query
            ->with('category:id,name')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [mb_substr($term, 0, 80).'%'])
            ->orderBy('name')
            ->take(6)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'image' => $p->image,
                'price' => (float) $p->price,
                'final_price' => $p->final_price,
                'has_discount' => $p->hasDiscount(),
                'category' => $p->category?->name,
                'in_stock' => $p->inStock(),
            ]);

        $categories = Category::where('name', 'like', $like)
            ->orderBy('name')
            ->take(3)
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'total' => $total,
        ]);
    }
}
