<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('test_categories', 'report_type')) {
                $after = Schema::hasColumn('test_categories', 'sort_order') ? 'sort_order' : 'is_active';
                $table->string('report_type', 32)->default('numeric')->after($after);
            }
        });
    }

    public function down(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            if (Schema::hasColumn('test_categories', 'report_type')) {
                $table->dropColumn('report_type');
            }
        });
    }
};
