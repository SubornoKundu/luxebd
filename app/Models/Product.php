<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected static function booted(): void
    {
        // The cached home page / best-seller blocks follow every catalogue change.
        static::saved(fn () => \App\Services\StorefrontCache::flush());
        static::deleted(fn () => \App\Services\StorefrontCache::flush());
    }

    /**
     * Accessors appended to the model's array/JSON form so the Vue
     * frontend never has to re-implement the "which price applies"
     * logic below -- it just reads `final_price`.
     *
     * @var list<string>
     */
    protected $appends = ['final_price'];

    /**
     * Lets product cards know whether a size/colour must be picked first.
     *
     * @var list<string>
     */
    protected $withCount = ['variants'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'specifications',
        'shipping_info',
        'price',
        'discount_price',
        'image',
        'stock',
        'is_featured',
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
            'specifications' => 'array',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'stock' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Look products up by slug in route model binding
     * (`/products/{product}`) instead of by numeric ID.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The category this product belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The brand this product belongs to.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Extra gallery pictures (the main picture is the `image` column).
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProductQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    /**
     * Wishlist entries referencing this product.
     *
     * @return HasMany<Wishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Cart entries referencing this product.
     *
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Line items (across all orders) referencing this product.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Reviews visible on the storefront.
     *
     * @return HasMany<Review, $this>
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    /**
     * Only products visible on the storefront.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Only products flagged for the "Featured Products" home section.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Whether this product currently has an active discount.
     */
    public function hasDiscount(): bool
    {
        return ! is_null($this->discount_price) && (float) $this->discount_price < (float) $this->price;
    }

    /**
     * The price a customer actually pays -- the discount price when one
     * is active, otherwise the regular price. Cart, wishlist, and order
     * totals are always calculated from this value.
     */
    public function getFinalPriceAttribute(): float
    {
        return $this->hasDiscount() ? (float) $this->discount_price : (float) $this->price;
    }

    /**
     * For a product with variants the shown stock is the sum of its variants;
     * call this after changing any variant's stock.
     */
    public function syncStockFromVariants(): void
    {
        if ($this->variants()->exists()) {
            $this->forceFill(['stock' => (int) $this->variants()->sum('stock')])->save();
        }
    }

    /**
     * Whether the product currently has stock available.
     */
    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}
