<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Services\CheckoutException;
use App\Services\CouponService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected CouponService $coupons,
    ) {}

    /**
     * Checkout page: order summary, coupon, shipping details and payment
     * method. Bounces back to the cart if there's nothing to check out.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $cartItems = $user->cartItems()->with('product')->get();
        $addresses = $user->addresses()->get();
        $default = $addresses->firstWhere('is_default', true) ?? $addresses->first();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $coupon = null;
        $code = $request->session()->get('checkout.coupon');

        if ($code) {
            try {
                $coupon = $this->coupons->validate($code, round((float) $cartItems->sum('line_total'), 2), $user);
            } catch (CheckoutException $e) {
                // The coupon stopped being valid (cart changed, expired...).
                $request->session()->forget('checkout.coupon');
                session()->flash('error', $e->getMessage());
            }
        }

        return Inertia::render('Checkout/Index', [
            'cartItems' => $cartItems,
            'totals' => $this->orders->totals($cartItems, $coupon),
            'coupon' => $coupon ? ['code' => $coupon->code, 'label' => $coupon->value_label] : null,
            'addresses' => $addresses,
            'defaults' => [
                'shipping_name' => $default?->name ?? $user->name,
                'shipping_phone' => $default?->phone ?? $user->phone,
                'shipping_address' => $default?->address ?? $user->address,
                'shipping_city' => $default?->city ?? $user->city,
            ],
            'paymentMethods' => $this->enabledMethods(),
            'advance' => [
                'percent' => OrderService::advancePercent(),
                'amount' => $this->orders->advanceFor((float) $this->orders->totals($cartItems, $coupon)['total'], Order::METHOD_BKASH),
            ],
            'manualPayments' => [
                'bkash' => config('shop.payments.bkash'),
                'nagad' => config('shop.payments.nagad'),
            ],
        ]);
    }

    /**
     * Place the order. Every method is confirmed straight away (bKash and Nagad are
     * checked by hand afterwards, cash is collected at the door).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:'.implode(',', $this->enabledMethods())],
            // bKash / Nagad (manual): the id from the payment confirmation SMS.
            'transaction_id' => ['nullable', 'required_if:payment_method,bkash,nagad', 'string', 'min:6', 'max:40'],
            // The bKash number the money was sent from (needed to find the payment).
            'payer_number' => [
                Rule::requiredIf(fn () => $request->input('payment_method') === 'bkash' && OrderService::advancePercent() > 0),
                'nullable', 'string', 'max:30',
                // Any usual way of writing a Bangladeshi mobile number: 01712345678, 01712-345678, +880 1712-345678, 8801712345678.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

                    if ($digits !== '' && ! preg_match('/^(?:880|0)1[3-9]\d{8}$/', $digits)) {
                        $fail('Enter a valid bKash number, like 01XXXXXXXXX.');
                    }
                },
            ],
            'save_address' => ['nullable', 'boolean'],
        ], [
            'payer_number.required' => 'Enter the bKash number you sent the advance from.',
        ]);

        // One payment can only pay for one order: a transaction id already on any order is refused.
        if (in_array($data['payment_method'], ['bkash', 'nagad'], true)) {
            $reference = strtoupper(trim((string) $data['transaction_id']));
            if (Order::where('transaction_id', $reference)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'transaction_id' => 'This transaction ID was already used for another order. Check the ID in your bKash SMS.',
                ]);
            }
        }

        $method = match ($data['payment_method']) {
            'cod' => Order::METHOD_COD,
            'bkash' => Order::METHOD_BKASH,
            'nagad' => Order::METHOD_NAGAD,
            default => Order::METHOD_COD,
        };

        try {
            $order = $this->orders->place(
                $request->user(),
                $data,
                $method,
                $request->session()->get('checkout.coupon'),
            );
        } catch (CheckoutException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        $request->session()->forget('checkout.coupon');

        if ($request->boolean('save_address')) {
            $this->rememberAddress($request->user(), $data);
        }

        return redirect()->route('orders.success', $order);
    }

    /**
     * Payment methods this shop accepts (config 'shop.payment_methods').
     *
     * @return list<string>
     */
    protected function enabledMethods(): array
    {
        $allowed = array_values(array_intersect((array) config('shop.payment_methods'), ['cod', 'bkash', 'nagad']));

        return $allowed ?: ['bkash'];
    }

    /**
     * Save the shipping details to the customer's address book (once).
     *
     * @param  array<string, mixed>  $data
     */
    protected function rememberAddress(\App\Models\User $user, array $data): void
    {
        $exists = $user->addresses()
            ->where('address', $data['shipping_address'])
            ->where('phone', $data['shipping_phone'])
            ->exists();

        if ($exists || $user->addresses()->count() >= 10) {
            return;
        }

        Address::create([
            'user_id' => $user->id,
            'label' => $user->addresses()->exists() ? 'Saved' : 'Home',
            'name' => $data['shipping_name'],
            'phone' => $data['shipping_phone'],
            'address' => $data['shipping_address'],
            'city' => $data['shipping_city'] ?? null,
            'is_default' => ! $user->addresses()->exists(),
        ]);
    }
}
