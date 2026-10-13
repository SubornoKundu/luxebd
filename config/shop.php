<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront Settings
    |--------------------------------------------------------------------------
    */

    // Flat shipping fee added to every order at checkout.
    'shipping_fee' => (float) env('SHOP_SHIPPING_FEE', 60),

    // Orders whose total (after discount) reaches this amount ship free.
    // Set to 0 to disable free shipping.
    'free_shipping_over' => (float) env('SHOP_FREE_SHIPPING_OVER', 5000),

    // Products with this many units (or fewer) left show up as "low stock"
    // on the admin dashboard.
    'low_stock_threshold' => (int) env('SHOP_LOW_STOCK_THRESHOLD', 5),

    'currency' => env('SHOP_CURRENCY', 'BDT'),

    'currency_symbol' => env('SHOP_CURRENCY_SYMBOL', '৳'),


    /*
    |--------------------------------------------------------------------------
    | Contact details, WhatsApp and social links (header / footer)
    |--------------------------------------------------------------------------
    */
    'contact' => [
        'phone' => env('SHOP_PHONE', '+880 1860-291022'),
        'email' => env('SHOP_EMAIL'),
        'address' => env('SHOP_ADDRESS', 'House 12, Road 5, Dhanmondi, Dhaka 1209, Bangladesh'),
        'hours' => env('SHOP_HOURS', 'Sat - Thu, 10:00 AM - 8:00 PM'),
    ],

    // International format without "+" or spaces, used for wa.me links.
    'whatsapp' => env('SHOP_WHATSAPP', '8801860291022'),

    'social' => [
        'facebook' => env('SHOP_FACEBOOK'),
        'instagram' => env('SHOP_INSTAGRAM'),
        'youtube' => env('SHOP_YOUTUBE'),
        'tiktok' => env('SHOP_TIKTOK'),
        'x' => env('SHOP_X'),
    ],

    // Shown in the "Shipping Terms" tab when a product has no text of its own.
    'default_shipping_info' => env(
        'SHOP_SHIPPING_INFO',
        "Inside Dhaka: delivery within 1-2 working days.\nOutside Dhaka: delivery within 3-5 working days.\nDelivery charge is added at checkout and is free on large orders.\nPayment is by bKash: send the total, then enter the transaction ID at checkout.\n7-day replacement if the bottle is damaged or not as described."
    ),

    // Manual mobile-banking numbers shown at checkout.
    'payments' => [
        'bkash' => ['number' => env('SHOP_BKASH_NUMBER', '01860291022'), 'type' => env('SHOP_BKASH_TYPE', 'Personal')],
        'nagad' => ['number' => env('SHOP_NAGAD_NUMBER', '01800-000000'), 'type' => 'Personal'],
    ],

    // Which payment methods checkout accepts: a comma list of cod, bkash, nagad.
    // Vireluxe takes manual bKash only (SHOP_PAYMENT_METHODS=bkash in .env). The automated tests
    // exercise every method, so they get the full list unless a test sets this itself.
    'payment_methods' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SHOP_PAYMENT_METHODS', app()->runningUnitTests() ? 'cod,bkash,nagad' : 'bkash'))
    ))),

    // Perfumes at or above this price (after discount) count as "premium"; below it, "everyday".
    // Off: the brand colour setting is ignored, so nobody can break the black and gold design from the admin.
    'brand_colour_editable' => (bool) env('SHOP_BRAND_COLOUR', env('APP_ENV') === 'testing'),

    // Which order events e-mail the customer: placed, paid, processing, shipped, completed, cancelled.
    // Comma separated in .env (SHOP_ORDER_EMAILS=processing sends only when the order is processed).
    'order_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SHOP_ORDER_EMAILS',
        'placed,paid,processing,shipped,completed,cancelled',
    ))))),

    // Part-payment for bKash orders: this % of the total is paid by bKash up front,
    // the rest in cash to the delivery man. 0 = the customer pays everything by bKash.
    'advance_percent' => (int) env('SHOP_ADVANCE_PERCENT', 0),

    // Drives the Premium / Everyday sections on the home page and the quick price filters.
    'premium_from' => (int) env('SHOP_PREMIUM_FROM', 4000),

    // What the "Visit us" map searches for (a Google Plus Code plus the area works well).
    'map_link' => env('SHOP_MAP_LINK', 'https://maps.app.goo.gl/KcakqGNL1S6FSmVT9'),
    'map_query' => env('SHOP_MAP_QUERY', '22.935250,91.336250'),

    // Scaling: how long (seconds) the home page blocks, the best-seller ranking and the
    // admin dashboard totals may be reused before they are recalculated. Admin edits to
    // products / brands / categories / banners refresh the storefront immediately.
    // 0 turns caching off (that is what the automated tests use).
    'storefront_cache_seconds' => (int) env('SHOP_STOREFRONT_CACHE', app()->runningUnitTests() ? 0 : 120),
    'popular_cache_seconds' => (int) env('SHOP_POPULAR_CACHE', app()->runningUnitTests() ? 0 : 600),
    'stats_cache_seconds' => (int) env('SHOP_STATS_CACHE', app()->runningUnitTests() ? 0 : 120),

    // Admin > Settings (shop name, logo, colours...) is hidden from the admin panel and its
    // address gives "not found" unless this is true. Keep it false on a site you hand over.
    // To change the logo or name yourself: set SHOP_SETTINGS_ACCESS=true in .env, save, edit, set it back.
    'settings_access' => (bool) env('SHOP_SETTINGS_ACCESS', false),

    // Behind Cloudflare / a load balancer / a hosting proxy: set TRUSTED_PROXIES=* (or a comma list
    // of proxy IPs) in .env so visitors' real IP addresses are used (login limits are per IP).
    'trusted_proxies' => env('TRUSTED_PROXIES'),
];
