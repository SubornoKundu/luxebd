<?php

namespace App\Http\Controllers;

use App\Services\CheckoutException;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(protected CouponService $coupons) {}

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);

        $user = $request->user();
        $subtotal = round((float) $user->cartItems()->with('product')->get()->sum('line_total'), 2);

        try {
            $coupon = $this->coupons->validate($data['code'], $subtotal, $user);
        } catch (CheckoutException $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->put('checkout.coupon', $coupon->code);

        return back()->with('success', "Coupon {$coupon->code} applied ({$coupon->value_label}).");
    }

    public function remove(Request $request): RedirectResponse
    {
        $request->session()->forget('checkout.coupon');

        return back()->with('success', 'Coupon removed.');
    }
}
