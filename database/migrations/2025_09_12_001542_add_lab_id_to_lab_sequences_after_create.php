<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lab_sequences') || Schema::hasColumn('lab_sequences', 'lab_id')) {
            return;
        }

        Schema::table('lab_sequences', function (Blueprint $table) {
            $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->onDelete('cascade');
            $table->index('lab_id');
        });

        DB::table('lab_sequences')->whereNull('lab_id')->update(['lab_id' => 1]);
        DB::statement('ALTER TABLE lab_sequences MODIFY lab_id BIGINT UNSIGNED NOT NULL');

        try {
            $indexes = DB::select("SHOW INDEX FROM lab_sequences WHERE Column_name = 'year' AND Non_unique = 0");
            if (!empty($indexes)) {
                $keyName = $indexes[0]->Key_name ?? null;
                if ($keyName) {
                    Schema::table('lab_sequences', function (Blueprint $table) use ($keyName) {
                        $table->dropUnique($keyName);
                    });
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        Schema::table('lab_sequences', function (Blueprint $table) {
            $table->unique(['lab_id', 'year'], 'lab_sequences_lab_id_year_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lab_sequences') || !Schema::hasColumn('lab_sequences', 'lab_id')) {
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
