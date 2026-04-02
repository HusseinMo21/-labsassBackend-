<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('test_categories', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            if (Schema::hasColumn('test_categories', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
