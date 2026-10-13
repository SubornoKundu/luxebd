<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
    ];

    public const NAV_CACHE_KEY = 'nav.categories';

    protected static function booted(): void
    {
        // Header menu is cached; any change in the category tree refreshes it.
        static::saved(function () {
            Cache::forget(self::NAV_CACHE_KEY);
            \App\Services\StorefrontCache::flush();
        });
        static::deleted(function () {
            Cache::forget(self::NAV_CACHE_KEY);
            \App\Services\StorefrontCache::flush();
        });
    }

    /**
     * Products that belong to this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    /**
     * This category's id plus the ids of its sub-categories -- used so that
     * opening "Electronics" also lists products filed under "Phones".
     *
     * @return list<int>
     */
    public function withDescendantIds(): array
    {
        return array_merge([$this->id], $this->children()->pluck('id')->all());
    }
}
