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
        Schema::table('test_categories', function (Blueprint $table) {
            $table->string('code')->default('')->after('name');
            $table->text('description')->nullable()->after('code');
            $table->boolean('is_active')->default(true)->after('description');
        });
        
        // Add unique constraint after adding the column
        Schema::table('test_categories', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'is_active']);
        });
    }
};
