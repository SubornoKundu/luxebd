<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class CouponService
{
    /**
     * Validate a coupon code for this user and cart subtotal, or throw a
     * CheckoutException explaining why it can't be used.
     */
    public function validate(string $code, float $subtotal, User $user): Coupon
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon || ! $coupon->is_active) {
            throw new CheckoutException('That coupon code is not valid.');
        }

        $today = today();

        if ($coupon->starts_at && $today->lt($coupon->starts_at)) {
            throw new CheckoutException('This coupon is not active yet.');
        }

        if ($coupon->expires_at && $today->gt($coupon->expires_at)) {
            throw new CheckoutException('This coupon has expired.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new CheckoutException('This coupon has reached its usage limit.');
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            throw new CheckoutException(
                'Add items worth '.config('shop.currency_symbol').number_format((float) $coupon->min_order_amount)
                .' or more to use this coupon.'
            );
        }

        if ($coupon->once_per_user && Order::where('user_id', $user->id)
            ->where('coupon_code', $coupon->code)
            ->where('stock_deducted', true)
            ->exists()) {
            throw new CheckoutException('You have already used this coupon.');
        }

        return $coupon;
    }

    /**
     * The BDT amount this coupon takes off the given subtotal.
     */
    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->type === Coupon::TYPE_PERCENT
            ? $subtotal * ((float) $coupon->value / 100)
            : (float) $coupon->value;

        if ($coupon->type === Coupon::TYPE_PERCENT && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
