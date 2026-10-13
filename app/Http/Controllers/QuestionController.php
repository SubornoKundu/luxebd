<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * A logged-in customer asks a question on a product page.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        // Light spam guard: a customer can ask 5 questions per product per day.
        $recent = ProductQuestion::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recent >= 5) {
            return back()->with('error', 'You have asked a lot of questions today. Please wait for an answer.');
        }

        ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'question' => trim($data['question']),
        ]);

        return back()->with('success', 'Thanks! Your question was posted. We will answer soon.');
    }
}
