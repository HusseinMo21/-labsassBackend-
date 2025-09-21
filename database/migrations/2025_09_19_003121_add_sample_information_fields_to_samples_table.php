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
        Schema::table('samples', function (Blueprint $table) {
            // Add sample information fields
            $table->string('case_type')->nullable()->comment('نوع الحالة - Case Type')->after('sample_type');
            $table->string('sample_size')->nullable()->comment('حجم العينة - Sample Size')->after('case_type');
            $table->integer('number_of_samples')->nullable()->comment('عدد العينات - Number of Samples')->after('sample_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropColumn(['case_type', 'sample_size', 'number_of_samples']);
        });
    }
};
