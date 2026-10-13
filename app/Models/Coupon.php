<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    protected $appends = ['value_label'];

    protected $fillable = [
        'code', 'type', 'value', 'min_order_amount', 'max_discount',
        'usage_limit', 'used_count', 'once_per_user', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'once_per_user' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'date:Y-m-d',
            'expires_at' => 'date:Y-m-d',
        ];
    }

    /** Codes are always stored (and matched) in upper case. */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value) => strtoupper(trim($value)));
    }

    /** Human-friendly discount, e.g. "10% off" or "৳200 off". */
    public function getValueLabelAttribute(): string
    {
        $value = (float) $this->value;
        $shown = floor($value) == $value ? (string) (int) $value : number_format($value, 2);

        return $this->type === self::TYPE_PERCENT
            ? $shown.'% off'
            : config('shop.currency_symbol').$shown.' off';
    }
}
