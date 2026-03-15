<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 1: Add lab_id to 5 tables only: users, patient, lab_requests, visits, invoices
     */
    public function up(): void
    {
        // 1. users - lab_id nullable (platform admins have NULL)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('set null');
                    $table->index('lab_id');
                }
            });
        }

        // 2. patient
        if (Schema::hasTable('patient')) {
            Schema::table('patient', function (Blueprint $table) {
                if (!Schema::hasColumn('patient', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                    $table->index('lab_id');
                }
            });
        }

        // 3. lab_requests
        if (Schema::hasTable('lab_requests')) {
            Schema::table('lab_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('lab_requests', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                    $table->index('lab_id');
                }
            });
        }

        // 4. visits
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                if (!Schema::hasColumn('visits', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                    $table->index('lab_id');
                }
            });
        }

        // 5. invoices
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'lab_id')) {
                    $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                    $table->index('lab_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lab_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }

        if (Schema::hasTable('patient') && Schema::hasColumn('patient', 'lab_id')) {
            Schema::table('patient', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }

        if (Schema::hasTable('lab_requests') && Schema::hasColumn('lab_requests', 'lab_id')) {
            Schema::table('lab_requests', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }

        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'lab_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'lab_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }
    }
};
