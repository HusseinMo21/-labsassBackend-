<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->nullable()->after('price_at_time');
            $table->foreignId('lab_package_id')
                ->nullable()
                ->constrained('lab_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visit_tests', function (Blueprint $table) {
            $table->dropForeign(['lab_package_id']);
            $table->dropColumn(['original_price', 'lab_package_id']);
        });
    }
};
