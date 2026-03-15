<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add lab_id to doctors and organizations for multi-tenant isolation.
     */
    public function up(): void
    {
        // doctors
        if (Schema::hasTable('doctors')) {
            Schema::table('doctors', function (Blueprint $table) {
                if (!Schema::hasColumn('doctors', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                    $table->index('lab_id');
                }
            });
            DB::table('doctors')->whereNull('lab_id')->update(['lab_id' => 1]);
            DB::statement('ALTER TABLE doctors MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }

        // organizations
        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (!Schema::hasColumn('organizations', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                    $table->index('lab_id');
                }
            });
            DB::table('organizations')->whereNull('lab_id')->update(['lab_id' => 1]);
            DB::statement('ALTER TABLE organizations MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doctors') && Schema::hasColumn('doctors', 'lab_id')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'lab_id')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }
    }
};
