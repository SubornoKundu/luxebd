<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Any of these may be empty, e.g. only "size" for a shoe.
            $table->string('size', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('type', 50)->nullable();
            // Added on top of the product price (and sale price).
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            // Optional photo shown when this variant is picked.
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
