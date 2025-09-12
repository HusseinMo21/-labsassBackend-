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
        if (Schema::hasTable('reports') && !Schema::hasColumn('reports', 'lab_request_id')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->unsignedBigInteger('lab_request_id')->nullable()->after('id');
                
                $table->foreign('lab_request_id')
                      ->references('id')
                      ->on('lab_requests')
                      ->onDelete('set null');
                      
                $table->index('lab_request_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'lab_request_id')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropForeign(['lab_request_id']);
                $table->dropIndex(['lab_request_id']);
                $table->dropColumn('lab_request_id');
            });
        }
    }
};
