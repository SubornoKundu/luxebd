<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    /**
     * View the logged-in user's wishlist.
     */
    public function index(Request $request): Response
    {
        $wishlists = $request->user()->wishlists()
            ->whereHas('product', fn ($q) => $q->active())
            ->with('product.brand')
            ->latest()
            ->get();

        return Inertia::render('Wishlist/Index', [
            'wishlists' => $wishlists,
        ]);
    }

    /**
     * Add a product to the wishlist (no-op if it's already there).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->active()->find($data['product_id']);

        if (! $product) {
            return back()->with('error', 'This product is no longer available.');
        }

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return back()->with('success', $wishlist->wasRecentlyCreated
            ? $product->name.' added to your wishlist.'
            : $product->name.' is already in your wishlist.');
    }

    /**
     * Remove a product from the wishlist.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $request->user()->wishlists()->where('product_id', $product->id)->delete();

        return back()->with('success', 'Removed from wishlist.');
    }
}
