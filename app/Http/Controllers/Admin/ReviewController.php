<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->input('status');

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => Review::with(['user:id,name,email', 'product:id,name,slug'])
                ->when($filter === 'hidden', fn ($q) => $q->where('is_approved', false))
                ->when($filter === 'visible', fn ($q) => $q->where('is_approved', true))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['status' => $filter ?? ''],
        ]);
    }

    public function toggle(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => ! $review->is_approved]);

        return back()->with('success', $review->is_approved ? 'Review is visible again.' : 'Review hidden from the store.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
