<?php

namespace App\Http\Controllers\Admin;

use App\Models\Slider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SliderController extends ResourceController
{
    protected function model(): string
    {
        return Slider::class;
    }

    protected function title(): string
    {
        return 'Banners';
    }

    protected function base(): string
    {
        return '/admin/sliders';
    }

    protected function searchColumn(): ?string
    {
        return 'title';
    }

    protected function query(Request $request): Builder
    {
        return parent::query($request)->reorder()->orderBy('sort_order')->orderBy('id');
    }

    protected function columns(): array
    {
        return [
            ['key' => 'image', 'label' => '', 'format' => 'image'],
            ['key' => 'title', 'label' => 'Title', 'format' => 'text'],
            ['key' => 'sort_order', 'label' => 'Order', 'format' => 'muted'],
            ['key' => 'is_active', 'label' => 'Status', 'format' => 'active'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'placeholder' => 'e.g. Eid Mega Sale', 'required' => true],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'placeholder' => 'e.g. Up to 40% off on fashion'],
            ['key' => 'image', 'label' => 'Banner picture', 'type' => 'image', 'requiredOnCreate' => true, 'previewWide' => true,
                'hint' => 'Best size: 1800 x 600 pixels (wide, 3:1). JPG, PNG or WebP, up to 6 MB. On computers the whole picture shows. On phones the left and right edges (about 12% each) are trimmed, so keep the text and buttons in the middle part. A different shape is trimmed to 3:1 automatically.'],
            ['key' => 'link', 'label' => 'Link (e.g. /products?category=fashion)', 'type' => 'text', 'placeholder' => '/products?category=fashion'],
            ['key' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'default' => 0],
            ['key' => 'is_active', 'label' => 'Show on home page', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image' => [$item ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'link' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#i'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
