<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
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
        'name',
        'slug',
        'logo',
    ];

    /**
     * Products that belong to this brand.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
