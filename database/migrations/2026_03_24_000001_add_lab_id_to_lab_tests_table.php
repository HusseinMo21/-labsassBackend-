<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreignId('lab_id')->nullable()->constrained('labs')->nullOnDelete();
            $table->index(['lab_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropForeign(['lab_id']);
            $table->dropIndex(['lab_id', 'code']);
            $table->dropColumn('lab_id');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
