<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add lab_id to audit_logs for multi-tenant filtering.
     */
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'lab_id')) {
                $table->foreignId('lab_id')->nullable()->after('user_id')->constrained('labs')->onDelete('set null');
                $table->index('lab_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'lab_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }
    }
};
