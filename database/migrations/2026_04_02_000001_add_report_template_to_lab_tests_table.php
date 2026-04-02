<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_tests', 'report_template')) {
                $table->json('report_template')->nullable()->after('reference_range');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            if (Schema::hasColumn('lab_tests', 'report_template')) {
                $table->dropColumn('report_template');
            }
        });
    }
};
