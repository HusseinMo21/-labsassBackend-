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
        Schema::table('lab_requests', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['patient_id']);
        });
        
        Schema::table('lab_requests', function (Blueprint $table) {
            // Change the data type to match the patient table
            $table->integer('patient_id')->nullable()->change();
        });
        
        Schema::table('lab_requests', function (Blueprint $table) {
            // Add the correct foreign key constraint pointing to the 'patient' table
            $table->foreign('patient_id')->references('id')->on('patient')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_requests', function (Blueprint $table) {
            // Drop the corrected foreign key constraint
            $table->dropForeign(['patient_id']);
        });
        
        Schema::table('lab_requests', function (Blueprint $table) {
            // Restore the original data type
            $table->unsignedBigInteger('patient_id')->nullable()->change();
        });
        
        Schema::table('lab_requests', function (Blueprint $table) {
            // Restore the original foreign key constraint pointing to 'patients' table
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
        });
    }
};