<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('test_categories', 'lab_id')) {
                $table->foreignId('lab_id')->nullable()->after('id')->constrained('labs')->nullOnDelete();
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE test_categories DROP INDEX test_categories_code_unique');
            } catch (\Throwable) {
                // Index name may differ or already removed
            }
        } elseif ($driver === 'sqlite') {
            // SQLite: recreate table if needed in dev — skip drop for simplicity
        }

        Schema::table('test_categories', function (Blueprint $table) {
            if (!$this->indexExists('test_categories', 'test_categories_lab_id_code_unique')) {
                $table->unique(['lab_id', 'code']);
            }
        });

        if (!Schema::hasTable('lab_test_category_settings')) {
            Schema::create('lab_test_category_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lab_id')->constrained('labs')->cascadeOnDelete();
                $table->foreignId('test_category_id')->constrained('test_categories')->cascadeOnDelete();
                $table->boolean('is_hidden')->default(false);
                $table->string('display_name')->nullable();
                $table->integer('sort_order')->nullable();
                $table->timestamps();
                $table->unique(['lab_id', 'test_category_id']);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return false;
        }
        $db = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$db, $table, $indexName]
        );

        return (bool) $row;
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_test_category_settings');

        if ($this->indexExists('test_categories', 'test_categories_lab_id_code_unique')) {
            Schema::table('test_categories', function (Blueprint $table) {
                $table->dropUnique(['lab_id', 'code']);
            });
        }

        Schema::table('test_categories', function (Blueprint $table) {
            if (Schema::hasColumn('test_categories', 'lab_id')) {
                $table->dropForeign(['lab_id']);
                $table->dropColumn('lab_id');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                Schema::table('test_categories', function (Blueprint $table) {
                    $table->unique('code');
                });
            } catch (\Throwable) {
            }
        }
    }
};
