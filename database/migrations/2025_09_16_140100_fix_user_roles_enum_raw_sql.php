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
        // Use raw SQL to modify the enum column
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff', 'doctor', 'patient') NOT NULL DEFAULT 'staff'");
        
        // Update existing data to match new enum values
        DB::table('users')->where('role', 'lab_tech')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'accountant')->update(['role' => 'staff']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the enum column
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'lab_tech', 'accountant', 'patient') NOT NULL DEFAULT 'lab_tech'");
        
        // Revert the data changes
        DB::table('users')->where('role', 'staff')->update(['role' => 'lab_tech']);
    }
};

