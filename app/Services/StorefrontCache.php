<?php

namespace App\Services;

use App\Models\Order;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Short-lived cache for the pages every visitor opens (home page, best-seller ranking).
 *
 * Without it each visit to the home page runs about ten database queries, including a
 * best-seller calculation over every order. With thousands of visitors a minute that
 * is the first thing to slow the database down. Saving or deleting a product, variant,
 * brand, category or banner calls flush(), so admin edits show up immediately; sales
 * figures are allowed to be a couple of minutes old.
 *
 * Switched off (0 seconds) while running tests, so tests always see fresh data.
 */
class StorefrontCache
{
    private const VERSION_KEY = 'storefront.version';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= (int) config('shop.storefront_cache_seconds');

        if ($ttl <= 0) {
            return $callback();
        }

        try {
            $version = (string) Cache::get(self::VERSION_KEY, '0');

            return Cache::remember("storefront.{$version}.{$key}", $ttl, fn () => self::plain($callback()));
        } catch (\Throwable $e) {
            // A broken cache must never take the shop down.
            report($e);

            return $callback();
        }
    }

    /**
     * Turn models / collections / objects into plain arrays and scalars.
     *
     * Laravel is configured not to restore objects from the cache
     * (config/cache.php "serializable_classes" => false), so a cached Eloquent
     * collection would come back as an unusable __PHP_Incomplete_Class and the
     * page would render empty. Plain arrays always survive. The result is exactly
     * what the object would have become in the JSON sent to the browser.
     */
    public static function plain(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Forget every cached storefront block (called when catalogue data changes). */
    public static function flush(): void
    {
        try {
            Cache::forever(self::VERSION_KEY, (string) microtime(true));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Ids of the best-selling products (cancelled orders ignored), best first.
     * One grouped query shared by the home page and the "popular" sort.
     *
     * @return list<int>
     */
    public static function popularProductIds(int $limit = 300): array
    {
        $ids = static function () use ($limit): array {
            return DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', '!=', Order::STATUS_CANCELLED)
                ->whereNotNull('order_items.product_id')
                ->groupBy('order_items.product_id')
                ->orderByRaw('SUM(order_items.quantity) DESC')
                ->orderByDesc('order_items.product_id')
                ->limit($limit)
                ->pluck('order_items.product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        };

        $ttl = (int) config('shop.popular_cache_seconds');

        if ($ttl <= 0) {
            return $ids();
        }

        try {
            return Cache::remember('storefront.popular-ids.'.$limit, $ttl, $ids);
        } catch (\Throwable $e) {
            report($e);

            return $ids();
        }
    }
}
