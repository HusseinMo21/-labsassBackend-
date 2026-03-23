<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            $table->foreignId('lab_test_offering_id')
                ->nullable()
                ->constrained('lab_test_offerings')
                ->nullOnDelete();
            $table->string('test_name_snapshot', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            $table->dropForeign(['lab_test_offering_id']);
            $table->dropColumn(['lab_test_offering_id', 'test_name_snapshot']);
        });
    }
};
