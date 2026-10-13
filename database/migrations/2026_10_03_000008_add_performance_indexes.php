<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that run on every page view or every admin page load.
 * Without them MySQL reads whole tables (every order, every product) to answer
 * things like "how many pending orders?" or "newest active products", which is
 * fine with 100 rows and painful with hundreds of thousands.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> table => index name => columns */
    private array $indexes = [
        'products' => [
            'products_active_created_idx' => ['is_active', 'created_at'],   // newest / featured listings
            'products_active_stock_idx' => ['is_active', 'stock'],          // low-stock counts
            'products_category_active_idx' => ['category_id', 'is_active'], // category pages
        ],
        'orders' => [
            'orders_status_idx' => ['status'],                              // pending-orders badge, status filter
            'orders_payment_created_idx' => ['payment_status', 'created_at'], // revenue / paid charts
            'orders_created_idx' => ['created_at'],                         // daily summaries
            'orders_user_coupon_idx' => ['user_id', 'coupon_code'],         // "coupon already used?" check
        ],
        'reviews' => [
            'reviews_product_approved_idx' => ['product_id', 'is_approved'],
        ],
        'product_questions' => [
            'product_questions_answered_idx' => ['answered_at'],            // open-questions badge
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
