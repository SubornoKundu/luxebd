/**
 * Formats a number (or numeric string -- Eloquent's decimal casts
 * serialize as strings, e.g. "1999.00") as a BDT price, e.g. ৳1,999.
 * Whole-taka amounts show no decimals; amounts with paisa (e.g. after a
 * percentage coupon) show two.
 */
export function formatPrice(amount) {
    const value = Number(amount) || 0;
    const whole = Math.abs(value - Math.round(value)) < 0.005;

    return '৳' + (whole
        ? Math.round(value).toLocaleString('en-US')
        : value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
}

/** "cod" -> "Cash on delivery", "bkash" -> "bKash", ... */
export function paymentMethodLabel(method) {
    return {
        cod: 'Cash on delivery',
        bkash: 'bKash',
        nagad: 'Nagad',
        rocket: 'Rocket',
        card: 'Card',
    }[method] ?? method;
}
