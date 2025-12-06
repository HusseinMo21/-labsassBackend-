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
        // Use raw SQL to change column type since doctrine/dbal might not be installed
        // This changes age column from integer to varchar(50) to store original format like "25M,5D"
        // Using full column definition to ensure it works properly
        DB::statement("ALTER TABLE `patient` MODIFY COLUMN `age` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to integer (note: this may cause data loss for non-numeric values)
        DB::statement('ALTER TABLE `patient` MODIFY COLUMN `age` INT NULL');
    }
};
