<?php

namespace Database\Seeders;

use App\Models\Slider;
use Illuminate\Database\Seeder;

/**
 * One-time reset of the home page banners: removes EVERY banner (and the picture files uploaded for them) and puts the two wide
 * Vireluxe banners in their place (3:1, 1792x592).
 *
 *   php artisan db:seed --class=VireluxeBannerSeeder
 *
 * Run it once. Afterwards add, replace, reorder or hide banners in Admin > Sliders.
 */
class VireluxeBannerSeeder extends Seeder
{
    public function run(): void
    {
        // Remove every banner, and the picture files that were uploaded for them in Admin.
        foreach (Slider::query()->pluck('image') as $image) {
            if (is_string($image) && str_starts_with($image, '/images/uploads/') && ! str_contains($image, '..')) {
                @unlink(public_path(ltrim($image, '/')));
            }
        }

        Slider::query()->delete();

        foreach ([
            ['Vireluxe Fragrance - Shop now', '/images/vireluxe/banners/banner-fragrance.jpg', 1],
            ['Luxury Perfume - Shop now', '/images/vireluxe/banners/banner-luxury.jpg', 2],
        ] as [$title, $image, $order]) {
            Slider::create([
                'title' => $title,
                'subtitle' => null,
                'link' => '/products',
                'image' => $image,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }
}
