<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to match new enum values
        DB::table('users')->where('role', 'lab_tech')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'accountant')->update(['role' => 'staff']);
        
        // Drop the old role column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        // Add the new role column with correct enum values
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'doctor', 'patient'])->default('staff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new role column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        // Add back the old role column
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'lab_tech', 'accountant', 'patient'])->default('lab_tech');
        });
        
        // Revert the data changes
        DB::table('users')->where('role', 'staff')->update(['role' => 'lab_tech']);
    }
};










