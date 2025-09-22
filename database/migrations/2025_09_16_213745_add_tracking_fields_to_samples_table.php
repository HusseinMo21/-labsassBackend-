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
        Schema::table('samples', function (Blueprint $table) {
            // Add tracking timestamps
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('disposed_at')->nullable();
            
            // Add user tracking fields
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('analyzed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('disposed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Add location field for sample tracking
            $table->string('location')->nullable();
            
            // Add status enum to include tracking states
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
            
            // Add indexes for better performance
            $table->index(['status']);
            $table->index(['lab_request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropColumn([
                'processing_started_at',
                'analysis_started_at', 
                'completed_at',
                'disposed_at',
                'collected_by',
                'received_by',
                'processed_by',
                'analyzed_by',
                'disposed_by',
                'location',
                'status'
            ]);
            
            $table->dropIndex(['status']);
            $table->dropIndex(['lab_request_id']);
        });
    }
};