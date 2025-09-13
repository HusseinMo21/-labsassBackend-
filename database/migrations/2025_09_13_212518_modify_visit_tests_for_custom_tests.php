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
        Schema::table('visit_tests', function (Blueprint $table) {
            // Add test category relationship
            $table->foreignId('test_category_id')->nullable()->constrained('test_categories')->onDelete('set null');
            
            // Add custom test name (when not using predefined lab test)
            $table->string('custom_test_name')->nullable();
            
            // Make lab_test_id nullable since we can have custom tests
            $table->foreignId('lab_test_id')->nullable()->change();
            
            // Add custom price (when not using predefined lab test price)
            $table->decimal('custom_price', 10, 2)->nullable();
            
            // Add discount fields
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            
            // Add final price after discount
            $table->decimal('final_price', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            $table->dropForeign(['test_category_id']);
            $table->dropColumn(['test_category_id', 'custom_test_name', 'custom_price', 'discount_amount', 'discount_percentage', 'final_price']);
            
            // Make lab_test_id required again
            $table->foreignId('lab_test_id')->nullable(false)->change();
        });
    }
};
