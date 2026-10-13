<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,answered,hidden'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $questions = ProductQuestion::query()
            ->with(['product:id,name,slug,image', 'user:id,name,email'])
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereNull('answered_at'))
            ->when(($filters['status'] ?? null) === 'answered', fn ($q) => $q->whereNotNull('answered_at'))
            ->when(($filters['status'] ?? null) === 'hidden', fn ($q) => $q->where('is_published', false))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('question', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Questions/Index', [
            'questions' => $questions,
            'filters' => ['status' => $filters['status'] ?? '', 'q' => $filters['q'] ?? ''],
            'counts' => [
                'open' => ProductQuestion::whereNull('answered_at')->count(),
                'all' => ProductQuestion::count(),
            ],
        ]);
    }

    public function answer(Request $request, ProductQuestion $question): RedirectResponse
    {
        $data = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $question->update([
            'answer' => trim($data['answer']),
            'answered_at' => now(),
            'is_published' => true,
        ]);

        return back()->with('success', 'Answer saved.');
    }

    public function toggle(ProductQuestion $question): RedirectResponse
    {
        $question->update(['is_published' => ! $question->is_published]);

        return back()->with('success', $question->is_published ? 'Question is visible again.' : 'Question hidden from the product page.');
    }

    public function destroy(ProductQuestion $question): RedirectResponse
    {
        $question->delete();

        return back()->with('success', 'Question deleted.');
    }
}
