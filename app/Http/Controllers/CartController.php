<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * View the logged-in user's cart, with a running total.
     */
    public function index(Request $request): Response
    {
        $cartItems = $request->user()->cartItems()
            ->with('product.brand')
            ->latest()
            ->get();

        return Inertia::render('Cart/Index', [
            'cartItems' => $cartItems,
            'total' => round($cartItems->sum('line_total'), 2),
        ]);
    }

    /**
     * Add a product (optionally a specific variant) to the cart. If it's
     * already there, the requested quantity is added on top. With `buy_now`
     * the customer goes straight on to checkout.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'buy_now' => ['nullable', 'boolean'],
        ]);

        $product = Product::query()->active()->find($data['product_id']);

        if (! $product) {
            return back()->with('error', 'This product is no longer available.');
        }

        $error = $this->addLine(
            $request->user(),
            $product,
            $data['variant_id'] ?? null,
            (int) ($data['quantity'] ?? 1),
            $request->boolean('buy_now'),
        );

        if ($error) {
            return back()->with('error', $error);
        }

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index');
        }

        return back()->with('success', $product->name.' added to your cart.');
    }

    /**
     * "Frequently bought together": add several products in one go.
     */
    public function storeBundle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:6'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        $added = 0;
        $problems = [];

        foreach ($data['items'] as $row) {
            $product = Product::query()->active()->find($row['product_id']);

            if (! $product) {
                continue;
            }

            $error = $this->addLine($request->user(), $product, $row['variant_id'] ?? null, 1);
            $error ? $problems[] = $error : $added++;
        }

        if ($added === 0) {
            return back()->with('error', $problems[0] ?? 'Nothing could be added to your cart.');
        }

        return back()->with('success', $added.' item'.($added === 1 ? '' : 's').' added to your cart.'
            .($problems ? ' Skipped: '.implode(' ', $problems) : ''));
    }

    /**
     * Update a cart line's quantity.
     */
    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $stock = $cartItem->available_stock;

        if ($stock < 1) {
            $cartItem->delete();

            return back()->with('error', $cartItem->product->name.' just sold out and was removed from your cart.');
        }

        $cartItem->update(['quantity' => min($data['quantity'], $stock)]);

        return back()->with('success', 'Cart updated.');
    }

    /**
     * Remove a line from the cart.
     */
    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $cartItem->delete();

        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Put one line into the cart. Returns an error message, or null on success.
     */
    protected function addLine(User $user, Product $product, ?int $variantId, int $quantity, bool $replace = false): ?string
    {
        $variant = null;

        if ($product->variants()->exists()) {
            if (! $variantId) {
                return 'Please choose an option (size / color) for '.$product->name.'.';
            }

            $variant = ProductVariant::where('product_id', $product->id)->find($variantId);

            if (! $variant) {
                return 'That option is not available for '.$product->name.'.';
            }
        }

        $available = $variant ? $variant->stock : $product->stock;

        if ($available < 1) {
            return $product->name.($variant ? ' ('.$variant->label.')' : '').' is currently out of stock.';
        }

        $cartItem = CartItem::firstOrNew([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
        ]);

        // "Order now" buys exactly the chosen quantity; "Add to cart" adds on top.
        $cartItem->quantity = min(($cartItem->exists && ! $replace ? $cartItem->quantity : 0) + $quantity, $available);
        $cartItem->save();

        return null;
    }
}
