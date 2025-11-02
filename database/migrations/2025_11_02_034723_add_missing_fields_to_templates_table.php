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
        Schema::table('templates', function (Blueprint $table) {
            // Add missing pathology detail fields
            if (!Schema::hasColumn('templates', 'specimen_information')) {
                $table->text('specimen_information')->nullable()->after('clinical_data');
            }
            if (!Schema::hasColumn('templates', 'gross_examination')) {
                $table->text('gross_examination')->nullable()->after('specimen_information');
            }
            if (!Schema::hasColumn('templates', 'microscopic_description')) {
                $table->text('microscopic_description')->nullable()->after('gross_examination');
            }
            if (!Schema::hasColumn('templates', 'referred_doctor')) {
                $table->string('referred_doctor')->nullable()->after('recommendations');
            }
            if (!Schema::hasColumn('templates', 'type_of_analysis')) {
                $table->string('type_of_analysis')->nullable()->after('referred_doctor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'specimen_information')) {
                $table->dropColumn('specimen_information');
            }
            if (Schema::hasColumn('templates', 'gross_examination')) {
                $table->dropColumn('gross_examination');
            }
            if (Schema::hasColumn('templates', 'microscopic_description')) {
                $table->dropColumn('microscopic_description');
            }
            if (Schema::hasColumn('templates', 'referred_doctor')) {
                $table->dropColumn('referred_doctor');
            }
            if (Schema::hasColumn('templates', 'type_of_analysis')) {
                $table->dropColumn('type_of_analysis');
            }
        });
    }
};
