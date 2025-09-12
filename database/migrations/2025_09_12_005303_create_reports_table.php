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
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lab_request_id')->nullable();
                $table->string('title');
                $table->text('content');
                $table->string('status')->default('draft');
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('lab_request_id')
                      ->references('id')
                      ->on('lab_requests')
                      ->onDelete('set null');
                      
                $table->foreign('generated_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                // Indexes
                $table->index(['lab_request_id', 'status']);
                $table->index('generated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
