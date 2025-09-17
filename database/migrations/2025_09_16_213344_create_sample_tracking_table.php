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
        Schema::create('sample_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_test_id')->constrained('visit_tests')->onDelete('cascade');
            $table->string('sample_id')->unique();
            $table->enum('status', [
                'collected', 
                'received', 
                'processing', 
                'analyzing', 
                'completed', 
                'disposed', 
                'lost', 
                'rejected'
            ])->default('collected');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            
            // Timestamps for each status
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('disposed_at')->nullable();
            
            // User IDs for each action
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('analyzed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('disposed_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['status']);
            $table->index(['visit_test_id']);
            $table->index(['sample_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sample_tracking');
    }
};