<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    // Order fulfilment status.
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    // Payment status.
    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    public const METHOD_COD = 'cod';

    public const METHOD_BKASH = 'bkash';
    public const METHOD_NAGAD = 'nagad';

    /**
     * Methods that are confirmed the moment the order is placed. bKash/Nagad
     * are manual: the customer sends money and types the transaction id, an
     * admin marks the payment as received later.
     *
     * @var list<string>
     */
    public const OFFLINE_METHODS = [self::METHOD_COD, self::METHOD_BKASH, self::METHOD_NAGAD];

    /**
     * Which statuses an order may move to next (admin workflow).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['can_cancel', 'next_statuses', 'tracking_steps', 'balance_due'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'coupon_code',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'transaction_id',
        'payer_number',
        'advance_amount',
        'stock_deducted',
        'delivered_at',
        'cancelled_at',
        'processed_at',
        'shipped_at',
        'courier',
        'tracking_code',
        'courier_booked_at',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'stock_deducted' => 'boolean',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'processed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'courier_booked_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
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
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * True when the customer paid part of the order by bKash up front and the
     * rest is collected in cash at the door.
     */
    public function hasAdvance(): bool
    {
        return (float) $this->advance_amount > 0;
    }

    /**
     * Cash the delivery man must collect. Cash on delivery: the whole total.
     * Advance orders: total minus the advance (zero once delivered). Fully
     * prepaid or already-paid orders: nothing.
     */
    public function getBalanceDueAttribute(): float
    {
        if ($this->status === self::STATUS_CANCELLED || $this->payment_status === self::PAYMENT_REFUNDED) {
            return 0.0;
        }

        $total = (float) $this->total_amount;

        if ($this->hasAdvance()) {
            return $this->status === self::STATUS_COMPLETED
                ? 0.0
                : max(0.0, round($total - (float) $this->advance_amount, 2));
        }

        return $this->isPaid() ? 0.0 : $total;
    }

    public function isCod(): bool
    {
        return $this->payment_method === self::METHOD_COD;
    }

    /**
     * Confirmed at placement (COD, manual bKash/Nagad) rather than after an
     * online payment.
     */
    public function isOffline(): bool
    {
        return in_array($this->payment_method, self::OFFLINE_METHODS, true);
    }

    /**
     * Customers may cancel only while the order hasn't been picked up for
     * processing and isn't already paid.
     */
    public function getCanCancelAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isPaid();
    }

    /**
     * @return list<string>
     */
    public function getNextStatusesAttribute(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    /**
     * Steps for the order-tracking timeline shown to customers.
     *
     * @return list<array{key: string, label: string, at: ?string, done: bool}>
     */
    public function getTrackingStepsAttribute(): array
    {
        $cancelled = $this->status === self::STATUS_CANCELLED;
        $rank = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'completed' => 3][$this->status] ?? 0;

        $steps = [
            ['key' => 'placed', 'label' => 'Order placed', 'at' => $this->created_at, 'done' => true],
            ['key' => 'processing', 'label' => 'Processing', 'at' => $this->processed_at, 'done' => ! $cancelled && $rank >= 1],
            ['key' => 'shipped', 'label' => 'Shipped', 'at' => $this->shipped_at, 'done' => ! $cancelled && $rank >= 2],
            ['key' => 'completed', 'label' => 'Delivered', 'at' => $this->delivered_at, 'done' => ! $cancelled && $rank >= 3],
        ];

        if ($cancelled) {
            $steps[] = ['key' => 'cancelled', 'label' => 'Cancelled', 'at' => $this->cancelled_at, 'done' => true];
        }

        return array_map(fn ($s) => [...$s, 'at' => $s['at']?->toIso8601String()], $steps);
    }

    /**
     * Generate a human-friendly, collision-safe order number, e.g.
     * "ORD-20260926-4F9K2A".
     */
    public static function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (self::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
