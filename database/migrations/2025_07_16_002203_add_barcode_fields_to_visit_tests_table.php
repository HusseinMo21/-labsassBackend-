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
            $table->string('barcode')->nullable()->after('lab_test_id');
            $table->string('sample_code')->nullable()->after('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'sample_code']);
        });
    }
};
