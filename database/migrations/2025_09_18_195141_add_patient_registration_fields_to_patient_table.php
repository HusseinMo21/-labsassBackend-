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
        Schema::table('patient', function (Blueprint $table) {
            // Sample and case information
            $table->string('sample_type')->nullable()->comment('نوع العينة - Sample Type');
            $table->string('case_type')->nullable()->comment('نوع الحالة - Case Type');
            $table->string('sample_size')->nullable()->comment('حجم العينة - Sample Size');
            $table->integer('number_of_samples')->nullable()->comment('عدد العينات - Number of Samples');
            $table->string('day_of_week')->nullable()->comment('اليوم - Day of Week');
            
            // Previous tests information
            $table->text('previous_tests')->nullable()->comment('هل سبق لك تحاليل باثولوجي - Previous Tests');
            
            // Attendance and delivery dates
            $table->date('attendance_date')->nullable()->comment('تاريخ الحضور - Attendance Date');
            $table->date('delivery_date')->nullable()->comment('ميعاد التسليم - Delivery Date');
            
            // Billing information
            $table->decimal('total_amount', 10, 2)->nullable()->comment('أجمالي المبلغ - Total Amount');
            $table->decimal('amount_paid', 10, 2)->nullable()->comment('المبلغ المدفوع - Amount Paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient', function (Blueprint $table) {
            $table->dropColumn([
                'sample_type',
                'case_type', 
                'sample_size',
                'number_of_samples',
                'day_of_week',
                'previous_tests',
                'attendance_date',
                'delivery_date',
                'total_amount',
                'amount_paid'
            ]);
        });
    }
};
