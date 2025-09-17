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
        Schema::table('users', function (Blueprint $table) {
            // Update the role enum to include staff and doctor
            $table->enum('role', ['admin', 'staff', 'doctor', 'patient', 'lab_tech', 'accountant'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert back to original enum
            $table->enum('role', ['admin', 'lab_tech', 'accountant', 'patient'])->change();
        });
    }
};
