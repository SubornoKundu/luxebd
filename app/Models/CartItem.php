<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /**
     * `line_total` needs the `product` relation loaded -- always eager
     * load it (`CartItem::with('product')`) before serializing a cart
     * item, otherwise this falls back to a lazy-loaded N+1 query per row.
     *
     * @var list<string>
     */
    protected $appends = ['line_total', 'unit_price', 'variant_label', 'available_stock'];

    /**
     * The variant is cheap to load and needed for price/stock everywhere.
     *
     * @var list<string>
     */
    protected $with = ['variant'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'variant_id',
        'quantity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Price of one unit: the product's final price plus the variant surcharge.
     */
    public function getUnitPriceAttribute(): float
    {
        return round($this->product->final_price + (float) ($this->variant?->extra_price ?? 0), 2);
    }

    public function getVariantLabelAttribute(): ?string
    {
        return $this->variant?->label;
    }

    /**
     * How many units can still be bought for this line (variant stock when a
     * variant is chosen, otherwise the product stock).
     */
    public function getAvailableStockAttribute(): int
    {
        return (int) ($this->variant ? $this->variant->stock : $this->product->stock);
    }

    /**
     * Line total for this cart row (unit price x quantity).
     */
    public function getLineTotalAttribute(): float
    {
        return round($this->unit_price * $this->quantity, 2);
    }
}
