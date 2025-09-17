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
        Schema::create('enhanced_reports', function (Blueprint $table) {
            $table->id();
            
            // Original fields (preserved from patholgy table)
            $table->string('nos')->nullable()->comment('Patient number/ID');
            $table->string('reff')->nullable()->comment('Reference number');
            $table->text('clinical')->nullable()->comment('Clinical information');
            $table->text('nature')->nullable()->comment('Nature of specimen');
            $table->date('report_date')->nullable()->comment('Report date');
            $table->string('lab_no')->nullable()->comment('Lab number');
            $table->string('age')->nullable()->comment('Patient age');
            $table->text('gross')->nullable()->comment('Gross examination findings');
            $table->text('micro')->nullable()->comment('Microscopic examination findings');
            $table->text('conc')->nullable()->comment('Conclusion');
            $table->text('reco')->nullable()->comment('Recommendation');
            $table->string('type')->nullable()->comment('Report type');
            $table->string('sex')->nullable()->comment('Patient gender');
            $table->string('recieving')->nullable()->comment('Receiving date');
            $table->string('discharge')->nullable()->comment('Discharge date');
            $table->boolean('confirm')->default(false)->comment('Confirmation status');
            $table->boolean('print')->default(false)->comment('Print status');
            
            // Enhanced fields (without foreign keys for now)
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('lab_request_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            
            // Enhanced status tracking
            $table->enum('status', ['draft', 'under_review', 'approved', 'printed', 'delivered'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            
            // Enhanced metadata
            $table->json('examination_details')->nullable()->comment('Detailed examination data');
            $table->json('quality_control')->nullable()->comment('Quality control checks');
            $table->string('barcode')->nullable()->comment('Report barcode');
            $table->string('digital_signature')->nullable()->comment('Digital signature hash');
            
            // Timestamps
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['lab_no', 'status']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['status', 'priority']);
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_reports');
    }
};

