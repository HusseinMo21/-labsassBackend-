<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('samples') && !Schema::hasColumn('samples', 'barcode')) {
            Schema::table('samples', function (Blueprint $table) {
                $table->string('barcode')->nullable()->after('lab_request_id');
                $table->string('sample_id')->nullable()->after('barcode'); // S1, S2, S3, etc.
                $table->index('barcode');
                $table->index('sample_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('samples') && Schema::hasColumn('samples', 'barcode')) {
            Schema::table('samples', function (Blueprint $table) {
                $table->dropIndex(['barcode']);
                $table->dropIndex(['sample_id']);
                $table->dropColumn(['barcode', 'sample_id']);
            });
        }
    }
};
