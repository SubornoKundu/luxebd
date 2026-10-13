<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends ResourceController
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function title(): string
    {
        return 'Categories';
    }

    protected function base(): string
    {
        return '/admin/categories';
    }

    protected function query(Request $request): Builder
    {
        return parent::query($request)->with('parent:id,name')->withCount('products');
    }

    protected function decorate($items): void
    {
        foreach ($items as $category) {
            $category->setAttribute('parent_name', $category->parent?->name ?? '—');
            $category->setAttribute('level', $category->parent_id ? 'Sub-category' : 'Top level');
        }
    }

    protected function columns(): array
    {
        return [
            ['key' => 'image', 'label' => '', 'format' => 'image'],
            ['key' => 'name', 'label' => 'Name', 'format' => 'text'],
            ['key' => 'parent_name', 'label' => 'Parent', 'format' => 'muted'],
            ['key' => 'products_count', 'label' => 'Products', 'format' => 'muted'],
            ['key' => 'slug', 'label' => 'Slug', 'format' => 'muted'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'placeholder' => 'e.g. Panjabi & Kurta', 'required' => true],
            [
                'key' => 'parent_id',
                'label' => 'Parent category (leave empty for a top-level category)',
                'type' => 'select',
                'default' => '',
                'options' => collect([['value' => '', 'label' => '— None (top-level) —']])
                    ->concat(Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name'])
                        ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]))
                    ->values()
                    ->all(),
            ],
            ['key' => 'image', 'label' => 'Category picture', 'type' => 'image',
                'hint' => 'Best size: 1200 x 900 pixels (4:3). JPG, PNG or WebP, up to 4 MB. A different shape is trimmed to 4:3 automatically, so keep the main thing in the middle.'],
        ];
    }

    protected function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($item?->id)],
            'parent_id' => [
                'nullable',
                'integer',
                // Only top-level categories can be parents (two levels), and a
                // category that has its own sub-categories can't become one.
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($item) {
                    if ($value && $item && (int) $value === $item->id) {
                        $fail('A category cannot be its own parent.');
                    }

                    if ($value && $item && $item->children()->exists()) {
                        $fail('This category has sub-categories, so it cannot be moved under another one.');
                    }
                },
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        if (! $item) {
            $data['slug'] = $this->uniqueSlug($data['name']);
        }

        $data['parent_id'] = $data['parent_id'] ?? null;

        return $data;
    }

    protected function deleteBlocker(Model $item): ?string
    {
        if ($item->products()->exists()) {
            return "Can't delete \"{$item->name}\" because it still has products. Move or delete them first.";
        }

        return $item->children()->exists()
            ? "Can't delete \"{$item->name}\" because it still has sub-categories. Delete or move them first."
            : null;
    }
}
