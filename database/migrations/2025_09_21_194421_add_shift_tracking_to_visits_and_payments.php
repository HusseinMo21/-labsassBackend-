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
        // Add shift tracking to visits table
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
                $table->foreignId('processed_by_staff')->nullable()->constrained('users')->onDelete('set null');
            });
        }

        // Add shift tracking to payments table
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            });
        }

        // Add shift tracking to invoices table
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove shift tracking from visits table
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->dropForeign(['processed_by_staff']);
                $table->dropColumn(['shift_id', 'processed_by_staff']);
            });
        }

        // Remove shift tracking from payments table
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->dropColumn('shift_id');
            });
        }

        // Remove shift tracking from invoices table
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->dropColumn('shift_id');
            });
        }
    }
};
