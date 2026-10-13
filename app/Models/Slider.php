<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected static function booted(): void
    {
        // The cached home page / best-seller blocks follow every catalogue change.
        static::saved(fn () => \App\Services\StorefrontCache::flush());
        static::deleted(fn () => \App\Services\StorefrontCache::flush());
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link',
        'sort_order',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Only sliders that should currently render on the home page hero.
     *
     * @param  Builder<Slider>  $query
     * @return Builder<Slider>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
