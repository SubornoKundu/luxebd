<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part-payment: the customer sends an advance by bKash and pays the rest in
 * cash to the delivery man. advance_amount is what was due by bKash at
 * checkout; payer_number is the bKash number the money was sent FROM (the
 * transaction id alone does not tell the admin whose payment to look for).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('advance_amount', 10, 2)->default(0)->after('total_amount');
            $table->string('payer_number', 20)->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['advance_amount', 'payer_number']);
        });
    }
};
