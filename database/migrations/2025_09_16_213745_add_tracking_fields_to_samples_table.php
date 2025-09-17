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
            $table->timestamp('processing_started_at')->nullable()->after('received_date');
            $table->timestamp('analysis_started_at')->nullable()->after('processing_started_at');
            $table->timestamp('completed_at')->nullable()->after('analysis_started_at');
            $table->timestamp('disposed_at')->nullable()->after('completed_at');
            
            // Add user tracking fields
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null')->after('disposed_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null')->after('collected_by');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null')->after('received_by');
            $table->foreignId('analyzed_by')->nullable()->constrained('users')->onDelete('set null')->after('processed_by');
            $table->foreignId('disposed_by')->nullable()->constrained('users')->onDelete('set null')->after('analyzed_by');
            
            // Add location field for sample tracking
            $table->string('location')->nullable()->after('disposed_by');
            
            // Update status enum to include more tracking states
            $table->enum('status', [
                'collected', 
                'received', 
                'processing', 
                'analyzing', 
                'completed', 
                'disposed', 
                'lost', 
                'rejected'
            ])->default('collected')->change();
            
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
                'location'
            ]);
            
            $table->dropIndex(['status']);
            $table->dropIndex(['lab_request_id']);
        });
    }
};