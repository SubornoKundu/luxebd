<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // A category with a parent_id is a sub-category (two levels deep).
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('categories')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            // [{"label": "Display", "value": "6.7 inch OLED"}, ...]
            $table->json('specifications')->nullable()->after('description');
            // Shipping terms tab; empty = show the store-wide default text.
            $table->text('shipping_info')->nullable()->after('specifications');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['specifications', 'shipping_info']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
