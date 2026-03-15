<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lab_sequences')) {
            return;
        }

        Schema::table('lab_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_sequences', 'lab_id')) {
                $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
                $table->index('lab_id');
            }
        });

        DB::table('lab_sequences')->whereNull('lab_id')->update(['lab_id' => 1]);

        DB::statement('ALTER TABLE lab_sequences MODIFY lab_id BIGINT UNSIGNED NOT NULL');

        // Drop old unique, add new
        try {
            Schema::table('lab_sequences', function (Blueprint $table) {
                $table->dropUnique(['year']);
            });
        } catch (\Exception $e) {
            // Index might have different name
        }

        Schema::table('lab_sequences', function (Blueprint $table) {
            $table->unique(['lab_id', 'year'], 'lab_sequences_lab_id_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lab_sequences')) {
            return;
        }

        Schema::table('lab_sequences', function (Blueprint $table) {
            $table->dropUnique('lab_sequences_lab_id_year_unique');
            $table->unique('year');
        });

        Schema::table('lab_sequences', function (Blueprint $table) {
            $table->dropForeign(['lab_id']);
            $table->dropColumn('lab_id');
        });
    }
};
