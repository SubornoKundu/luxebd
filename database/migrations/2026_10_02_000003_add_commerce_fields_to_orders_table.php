<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('shipping_fee');
            $table->string('coupon_code')->nullable()->after('discount_amount');
            // True once stock has been taken out of inventory for this order
            // (COD: at placement, online: after successful payment). Also
            // marks a coupon use as "consumed".
            $table->boolean('stock_deducted')->default(false)->after('transaction_id');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'coupon_code', 'stock_deducted', 'delivered_at', 'cancelled_at']);
        });
    }
};
