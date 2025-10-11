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
        // Check if age column exists and is the correct type
        if (Schema::hasColumn('patient', 'age')) {
            // Get current column type
            $columnType = DB::select("SHOW COLUMNS FROM patient WHERE Field = 'age'")[0]->Type ?? null;
            
            // If it's not an integer, modify it
            if ($columnType && !str_contains($columnType, 'int')) {
                Schema::table('patient', function (Blueprint $table) {
                    $table->integer('age')->nullable()->change();
                });
            }
        } else {
            // Add age column if it doesn't exist
            Schema::table('patient', function (Blueprint $table) {
                $table->integer('age')->nullable()->after('gender');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop the age column as it's a core field
    }
};

