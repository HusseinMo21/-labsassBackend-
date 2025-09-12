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
        if (!Schema::hasTable('samples')) {
            Schema::create('samples', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lab_request_id');
                $table->string('tsample', 255)->nullable();
                $table->string('nsample', 255)->nullable();
                $table->string('isample', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('lab_request_id')
                      ->references('id')
                      ->on('lab_requests')
                      ->onDelete('cascade');

                // Index for performance
                $table->index(['lab_request_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};
