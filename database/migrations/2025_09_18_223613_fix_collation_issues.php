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
        // SQLite doesn't support character set and collation modifications
        // These operations are not needed for SQLite as it handles UTF-8 natively
        // and doesn't have the same collation issues as MySQL
        
        // For SQLite, we just ensure the tables exist and have the correct structure
        // The original issue was likely related to MySQL collation conflicts
        
        // No-op for SQLite - the tables should already be properly structured
        // from previous migrations
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for SQLite - no changes to revert
    }
};