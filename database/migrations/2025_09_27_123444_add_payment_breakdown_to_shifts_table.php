<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->decimal('cash_collected', 10, 2)->default(0)->after('total_collected');
            $table->decimal('other_payments_collected', 10, 2)->default(0)->after('cash_collected');
            $table->json('payment_breakdown')->nullable()->after('other_payments_collected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['cash_collected', 'other_payments_collected', 'payment_breakdown']);
        });
    }
};
