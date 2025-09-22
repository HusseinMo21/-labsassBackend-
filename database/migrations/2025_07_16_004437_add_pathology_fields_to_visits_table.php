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
        Schema::table('visits', function (Blueprint $table) {
            $table->text('clinical_data')->nullable();
            $table->text('microscopic_description')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('referred_doctor')->nullable();
            $table->enum('test_status', ['pending', 'under_review', 'completed'])->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['clinical_data', 'microscopic_description', 'diagnosis', 'recommendations', 'referred_doctor', 'test_status']);
        });
    }
};
