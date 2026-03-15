<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add lab_id to patient_credentials; change username unique to (lab_id, username).
     */
    public function up(): void
    {
        if (!Schema::hasTable('patient_credentials')) {
            return;
        }

        Schema::table('patient_credentials', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_credentials', 'lab_id')) {
                $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                $table->index('lab_id');
            }
        });

        // Backfill lab_id from patient
        if (Schema::hasColumn('patient_credentials', 'lab_id') && Schema::hasColumn('patient', 'lab_id')) {
            DB::statement('
                UPDATE patient_credentials pc
                JOIN patient p ON pc.patient_id = p.id
                SET pc.lab_id = p.lab_id
                WHERE pc.lab_id IS NULL
            ');
        }

        // Make lab_id NOT NULL after backfill
        if (Schema::hasTable('patient_credentials') && Schema::hasColumn('patient_credentials', 'lab_id')) {
            DB::table('patient_credentials')->whereNull('lab_id')->update(['lab_id' => 1]);
            DB::statement('ALTER TABLE patient_credentials MODIFY lab_id BIGINT UNSIGNED NOT NULL');
        }

        // Drop old username unique, add (lab_id, username) unique
        try {
            Schema::table('patient_credentials', function (Blueprint $table) {
                $table->dropUnique(['username']);
            });
        } catch (\Exception $e) {
            // May have different index name
            $indexes = DB::select("SHOW INDEX FROM patient_credentials WHERE Column_name = 'username' AND Non_unique = 0");
            if (!empty($indexes)) {
                $keyName = $indexes[0]->Key_name ?? null;
                if ($keyName) {
                    Schema::table('patient_credentials', function (Blueprint $table) use ($keyName) {
                        $table->dropUnique($keyName);
                    });
                }
            }
        }

        Schema::table('patient_credentials', function (Blueprint $table) {
            $table->unique(['lab_id', 'username'], 'patient_credentials_lab_id_username_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patient_credentials')) {
            return;
        }

        Schema::table('patient_credentials', function (Blueprint $table) {
            $table->dropUnique('patient_credentials_lab_id_username_unique');
            $table->unique('username');
        });

        if (Schema::hasColumn('patient_credentials', 'lab_id')) {
            Schema::table('patient_credentials', function (Blueprint $table) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            });
        }
    }
};
