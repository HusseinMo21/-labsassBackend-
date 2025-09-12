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
        // Add a temporary column to store the new role
        Schema::table('users', function (Blueprint $table) {
            $table->string('new_role')->nullable();
        });
        
        // Map old roles to new roles
        DB::table('users')->where('role', 'admin')->update(['new_role' => 'admin']);
        DB::table('users')->where('role', 'lab_tech')->update(['new_role' => 'staff']);
        DB::table('users')->where('role', 'accountant')->update(['new_role' => 'staff']);
        DB::table('users')->where('role', 'patient')->update(['new_role' => 'patient']);
        
        // Drop the old role column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        // Rename new_role to role with new enum constraint
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'doctor', 'patient'])->default('staff');
        });
        
        // Copy data from new_role to role
        DB::statement('UPDATE users SET role = new_role');
        
        // Drop the temporary column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('new_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the role changes
        DB::table('users')->where('role', 'staff')->update(['role' => 'lab_tech']);
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'lab_tech', 'accountant', 'patient'])->default('lab_tech');
        });
    }
};
