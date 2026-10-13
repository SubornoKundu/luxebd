<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            // courier name (entered by hand)
            $table->string('courier', 30)->nullable();
            $table->string('tracking_code', 60)->nullable();
            $table->timestamp('courier_booked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['processed_at', 'shipped_at', 'courier', 'tracking_code', 'courier_booked_at']);
        });
    }
};
