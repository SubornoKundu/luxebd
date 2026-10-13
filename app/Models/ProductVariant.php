<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected static function booted(): void
    {
        // The cached home page / best-seller blocks follow every catalogue change.
        static::saved(fn () => \App\Services\StorefrontCache::flush());
        static::deleted(fn () => \App\Services\StorefrontCache::flush());
    }

    /** @var list<string> */
    protected $appends = ['label'];

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'size',
        'color',
        'type',
        'extra_price',
        'stock',
        'image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
            'stock' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Human readable name, e.g. "Size 42 / Black".
     */
    public function getLabelAttribute(): string
    {
        return collect([
            $this->type,
            $this->size ? 'Size '.$this->size : null,
            $this->color,
        ])->filter()->implode(' / ') ?: 'Default';
    }
}
