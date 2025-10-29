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
            // Report delivery tracking
            $table->boolean('report_delivered')->default(false)->after('type');
            $table->date('report_delivery_date')->nullable()->after('report_delivered');
            $table->text('report_delivery_notes')->nullable()->after('report_delivery_date');
            $table->string('report_delivered_by')->nullable()->after('report_delivery_notes'); // Who received it
            
            // Wax blocks (بلوكات الشمع) tracking
            $table->boolean('wax_blocks_delivered')->default(false)->after('report_delivered_by');
            $table->date('wax_blocks_delivery_date')->nullable()->after('wax_blocks_delivered');
            $table->text('wax_blocks_delivery_notes')->nullable()->after('wax_blocks_delivery_date');
            $table->string('wax_blocks_delivered_by')->nullable()->after('wax_blocks_delivery_notes'); // Who received it
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient', function (Blueprint $table) {
            $table->dropColumn([
                'report_delivered',
                'report_delivery_date',
                'report_delivery_notes',
                'report_delivered_by',
                'wax_blocks_delivered',
                'wax_blocks_delivery_date',
                'wax_blocks_delivery_notes',
                'wax_blocks_delivered_by'
            ]);
        });
    }
};
