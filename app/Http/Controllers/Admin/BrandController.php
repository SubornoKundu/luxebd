<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BrandController extends ResourceController
{
    protected function model(): string
    {
        return Brand::class;
    }

    protected function title(): string
    {
        return 'Brands';
    }

    protected function base(): string
    {
        return '/admin/brands';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'logo', 'label' => '', 'format' => 'image'],
            ['key' => 'name', 'label' => 'Name', 'format' => 'text'],
            ['key' => 'slug', 'label' => 'Slug', 'format' => 'muted'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'placeholder' => 'e.g. Walton', 'required' => true],
            ['key' => 'logo', 'label' => 'Brand logo', 'type' => 'image',
                'hint' => 'Best: a PNG with a transparent background, about 600 pixels wide. JPG, PNG or WebP, up to 4 MB. Empty space around the logo is trimmed automatically so it fills its box.'],
        ];
    }

    protected function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($item?->id)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        if (! $item) {
            $data['slug'] = $this->uniqueSlug($data['name']);
        }

        return $data;
    }

    protected function deleteBlocker(Model $item): ?string
    {
        return $item->products()->exists()
            ? "Can't delete \"{$item->name}\" because it still has products. Move or delete them first."
            : null;
    }
}
