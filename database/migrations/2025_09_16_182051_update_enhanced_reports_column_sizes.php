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
        Schema::table('enhanced_reports', function (Blueprint $table) {
            // Change varchar fields to text to match patholgy table
            $table->text('nos')->change();
            $table->text('reff')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enhanced_reports', function (Blueprint $table) {
            // Revert back to varchar
            $table->string('nos')->change();
            $table->string('reff')->change();
        });
    }
};
