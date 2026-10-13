<?php

namespace App\Services;

use RuntimeException;

/**
 * A business-rule failure during checkout / order handling whose message is
 * safe to show to the customer (out of stock, invalid coupon, ...).
 */
class CheckoutException extends RuntimeException {}
