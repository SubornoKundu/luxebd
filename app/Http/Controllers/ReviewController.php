<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Create or update the logged-in customer's review of a product they
     * have received.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        if (! ProductController::hasPurchased($user->id, $product->id)) {
            return back()->with('error', 'You can review a product after your order with it has been delivered.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        Review::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $product->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null],
        );

        return back()->with('success', 'Thanks for your review!');
    }
}
