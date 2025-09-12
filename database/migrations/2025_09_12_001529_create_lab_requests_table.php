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
        if (!Schema::hasTable('lab_requests')) {
            Schema::create('lab_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patient_id')->nullable();
                $table->string('lab_no', 100)->index();
                $table->enum('suffix', ['m', 'h'])->nullable();
                $table->enum('status', [
                    'pending',
                    'received', 
                    'in_progress',
                    'under_review',
                    'completed',
                    'delivered'
                ])->default('pending');
                $table->json('metadata')->nullable();
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('patient_id')
                      ->references('id')
                      ->on('patients')
                      ->onDelete('set null');

                // Unique constraint for lab_no + suffix combination
                $table->unique(['lab_no', 'suffix'], 'lab_requests_lab_no_suffix_unique');
                
                // Index for performance
                $table->index(['status', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_requests');
    }
};
