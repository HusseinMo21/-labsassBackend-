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
        Schema::table('patient', function (Blueprint $table) {
            // Add missing fields that are used in the form but not in the table
            if (!Schema::hasColumn('patient', 'medical_history')) {
                $table->text('medical_history')->nullable()->comment('Medical history information');
            }
            if (!Schema::hasColumn('patient', 'allergies')) {
                $table->text('allergies')->nullable()->comment('Patient allergies');
            }
            if (!Schema::hasColumn('patient', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable()->comment('Emergency contact name');
            }
            if (!Schema::hasColumn('patient', 'emergency_phone')) {
                $table->string('emergency_phone')->nullable()->comment('Emergency contact phone');
            }
            if (!Schema::hasColumn('patient', 'birth_date')) {
                $table->date('birth_date')->nullable()->comment('Patient birth date');
            }
            if (!Schema::hasColumn('patient', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('patient', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient', function (Blueprint $table) {
            $table->dropColumn([
                'medical_history',
                'allergies',
                'emergency_contact',
                'emergency_phone',
                'birth_date',
                'user_id',
                'created_at',
                'updated_at'
            ]);
        });
    }
};
