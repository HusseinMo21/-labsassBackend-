<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->decimal('upfront_payment', 10, 2)->default(0)->after('final_amount');
            $table->decimal('remaining_balance', 10, 2)->default(0)->after('upfront_payment');
            $table->decimal('minimum_upfront_percentage', 5, 2)->default(50)->after('remaining_balance');
            $table->string('payment_method')->nullable()->after('minimum_upfront_percentage');
            $table->string('receipt_number')->unique()->nullable()->after('payment_method');
            $table->date('expected_delivery_date')->nullable()->after('receipt_number');
            $table->string('barcode')->unique()->nullable()->after('expected_delivery_date');
            $table->string('check_in_by')->nullable()->after('barcode');
            $table->timestamp('check_in_at')->nullable()->after('check_in_by');
            $table->string('billing_status')->default('pending')->after('check_in_at'); // pending, partial, paid
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn([
                'upfront_payment', 'remaining_balance', 'minimum_upfront_percentage',
                'payment_method', 'receipt_number', 'expected_delivery_date',
                'barcode', 'check_in_by', 'check_in_at', 'billing_status'
            ]);
        });
    }
}; 