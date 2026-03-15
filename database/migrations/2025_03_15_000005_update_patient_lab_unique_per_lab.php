<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change patient.lab from global unique to (lab_id, lab) unique per lab.
     */
    public function up(): void
    {
        if (!Schema::hasTable('patient') || !Schema::hasColumn('patient', 'lab_id')) {
            return;
        }

        // Drop old unique constraint
        try {
            Schema::table('patient', function (Blueprint $table) {
                $table->dropUnique('patient_lab_unique');
            });
        } catch (\Exception $e) {
            // Constraint might not exist or have different name
        }

        // Add unique (lab_id, lab) - allow same lab number across different labs
        Schema::table('patient', function (Blueprint $table) {
            $table->unique(['lab_id', 'lab'], 'patient_lab_id_lab_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patient')) {
            return;
        }

        Schema::table('patient', function (Blueprint $table) {
            $table->dropUnique('patient_lab_id_lab_unique');
            $table->unique('lab', 'patient_lab_unique');
        });
    }
};
