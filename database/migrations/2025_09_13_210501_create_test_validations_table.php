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
        Schema::create('test_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_test_id')->constrained('visit_tests')->onDelete('cascade');
            $table->enum('validation_type', ['initial', 'review', 'final']);
            $table->enum('status', ['pending', 'validated', 'rejected', 'requires_correction'])->default('pending');
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('correction_notes')->nullable();
            $table->text('clinical_correlation')->nullable();
            $table->boolean('reference_range_check')->default(false);
            $table->boolean('critical_value_check')->default(false);
            $table->boolean('delta_check')->default(false);
            $table->boolean('technical_quality')->default(false);
            $table->boolean('result_consistency')->default(false);
            $table->text('validation_notes')->nullable();
            $table->timestamps();

            $table->index(['visit_test_id', 'validation_type']);
            $table->index(['status', 'validated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_validations');
    }
};
