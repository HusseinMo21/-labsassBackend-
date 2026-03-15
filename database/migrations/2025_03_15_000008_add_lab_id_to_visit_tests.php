<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add lab_id to visit_tests; change barcode_uid unique to (lab_id, barcode_uid).
     */
    public function up(): void
    {
        if (!Schema::hasTable('visit_tests')) {
            return;
        }

        Schema::table('visit_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('visit_tests', 'lab_id')) {
                $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                $table->index('lab_id');
            }
        });

        // Backfill lab_id from visit
        if (Schema::hasColumn('visit_tests', 'lab_id') && Schema::hasColumn('visits', 'lab_id')) {
            DB::statement('
                UPDATE visit_tests vt
                JOIN visits v ON vt.visit_id = v.id
                SET vt.lab_id = v.lab_id
                WHERE vt.lab_id IS NULL
            ');
        }

        // Make lab_id NOT NULL after backfill
        if (Schema::hasTable('visit_tests') && Schema::hasColumn('visit_tests', 'lab_id')) {
            DB::table('visit_tests')->whereNull('lab_id')->update(['lab_id' => 1]);
            DB::statement('ALTER TABLE visit_tests MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }

        // Drop old barcode_uid unique, add (lab_id, barcode_uid) unique
        try {
            $indexes = DB::select("SHOW INDEX FROM visit_tests WHERE Column_name = 'barcode_uid' AND Non_unique = 0");
            if (!empty($indexes)) {
                $keyName = $indexes[0]->Key_name ?? null;
                if ($keyName) {
                    Schema::table('visit_tests', function (Blueprint $table) use ($keyName) {
                        $table->dropUnique($keyName);
                    });
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        Schema::table('visit_tests', function (Blueprint $table) {
            $table->unique(['lab_id', 'barcode_uid'], 'visit_tests_lab_id_barcode_uid_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('visit_tests')) {
            return;
        }

        Schema::table('visit_tests', function (Blueprint $table) {
            $table->dropUnique('visit_tests_lab_id_barcode_uid_unique');
            $table->unique('barcode_uid');
        });

        if (Schema::hasColumn('visit_tests', 'lab_id')) {
            Schema::table('visit_tests', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }
    }
};
