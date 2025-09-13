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
        Schema::create('quality_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_test_id')->constrained('visit_tests')->onDelete('cascade');
            $table->enum('qc_type', ['pre_test', 'post_test', 'batch_control']);
            $table->enum('status', ['pending', 'passed', 'failed', 'requires_review'])->default('pending');
            $table->string('control_sample_id')->nullable();
            $table->decimal('expected_value', 10, 4)->nullable();
            $table->decimal('actual_value', 10, 4)->nullable();
            $table->decimal('tolerance_range', 8, 2)->nullable();
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('performed_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('equipment_used')->nullable();
            $table->string('reagent_lot_number')->nullable();
            $table->date('reagent_expiry_date')->nullable();
            $table->timestamps();

            $table->index(['visit_test_id', 'qc_type']);
            $table->index(['status', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_controls');
    }
};
