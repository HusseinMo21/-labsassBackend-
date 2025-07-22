<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_test_id')->constrained('visit_tests')->onDelete('cascade');
            $table->string('sample_id')->unique(); // Unique sample identifier
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
            $table->string('location')->nullable(); // Current location
            $table->text('notes')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->foreignId('analyzed_by')->nullable()->constrained('users');
            $table->foreignId('disposed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_tracking');
    }
}; 