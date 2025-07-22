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
            $table->text('clinical_data')->nullable()->after('remarks');
            $table->text('microscopic_description')->nullable()->after('clinical_data');
            $table->text('diagnosis')->nullable()->after('microscopic_description');
            $table->text('recommendations')->nullable()->after('diagnosis');
            $table->string('referred_doctor')->nullable()->after('recommendations');
            $table->enum('test_status', ['pending', 'under_review', 'completed'])->default('pending')->after('referred_doctor');
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
