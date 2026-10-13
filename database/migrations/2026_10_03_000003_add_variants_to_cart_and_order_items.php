<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->cascadeOnDelete();
            // Starts with user_id, so it can also serve the user_id foreign key.
            $table->index(['user_id', 'product_id', 'variant_id'], 'cart_items_user_product_variant_index');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            // The same product may now sit in the cart once per variant.
            $table->dropUnique(['user_id', 'product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
            // Snapshot, e.g. "Size 42 / Black".
            $table->string('variant_label')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
            $table->dropColumn('variant_label');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_user_product_variant_index');
            $table->unique(['user_id', 'product_id']);
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
