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
        Schema::table('inventory_items', function (Blueprint $table) {
            // Add lab-specific fields
            $table->enum('category', ['reagents', 'consumables', 'equipment', 'pathology', 'cytology', 'ihc', 'other'])->default('reagents')->after('name');
            $table->string('batch_number')->nullable()->after('supplier');
            $table->string('lot_number')->nullable()->after('batch_number');
            $table->string('storage_conditions')->nullable()->after('lot_number');
            $table->enum('hazard_level', ['low', 'medium', 'high', 'critical'])->default('low')->after('storage_conditions');
            $table->string('temperature_range')->nullable()->after('hazard_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'batch_number',
                'lot_number',
                'storage_conditions',
                'hazard_level',
                'temperature_range'
            ]);
        });
    }
};
