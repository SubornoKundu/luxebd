<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra picture in a product's photo gallery (the main picture lives in products.image). */
class ProductImage extends Model
{
    /** @var list<string> */
    protected $fillable = ['product_id', 'path', 'sort_order'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
