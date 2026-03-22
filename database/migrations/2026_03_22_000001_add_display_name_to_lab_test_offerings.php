<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_test_offerings', function (Blueprint $table) {
            $table->string('display_name', 255)->nullable()->after('lab_test_id');
        });
    }

    public function down(): void
    {
        Schema::table('lab_test_offerings', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
