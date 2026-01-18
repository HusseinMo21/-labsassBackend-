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
        // For MySQL, we need to modify the enum column
        // First, modify the column to allow all new values
        DB::statement("ALTER TABLE visits MODIFY COLUMN test_status ENUM('pending', 'in_progress', 'under_review', 'approved', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE visits MODIFY COLUMN test_status ENUM('pending', 'under_review', 'completed') DEFAULT 'pending'");
    }
};
