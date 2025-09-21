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
        // Create users table first (no dependencies)
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->enum('role', ['admin', 'staff', 'doctor', 'patient', 'lab_tech', 'accountant']);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Create personal_access_tokens table
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // Create refresh_tokens table
        if (!Schema::hasTable('refresh_tokens')) {
            Schema::create('refresh_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('token', 64)->unique();
                $table->string('device_id')->nullable();
                $table->string('device_name')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_revoked')->default(false);
                $table->timestamps();
            });
        }

        // Create test_categories table
        if (!Schema::hasTable('test_categories')) {
            Schema::create('test_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create lab_tests table
        if (!Schema::hasTable('lab_tests')) {
            Schema::create('lab_tests', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);
                $table->string('unit')->nullable();
                $table->string('reference_range')->nullable();
                $table->text('preparation_instructions')->nullable();
                $table->integer('turnaround_time_hours')->default(24);
                $table->foreignId('category_id')->constrained('test_categories');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create doctors table
        if (!Schema::hasTable('doctors')) {
            Schema::create('doctors', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('specialization')->nullable();
                $table->string('phone', 100)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('license_number', 100)->nullable();
                $table->timestamps();
            });
        }

        // Create organizations table
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('type', 100)->nullable();
                $table->string('phone', 100)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('contact_person')->nullable();
                $table->timestamps();
            });
        }

        // Create patient table
        if (!Schema::hasTable('patient')) {
            Schema::create('patient', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->nullable();
                $table->string('address')->nullable();
                $table->string('entry', 100)->nullable();
                $table->string('deli', 100)->nullable();
                $table->string('time')->nullable();
                $table->integer('age')->nullable();
                $table->string('phone', 100)->nullable();
                $table->string('whatsapp_number', 100)->nullable();
                $table->string('tsample', 100)->nullable();
                $table->string('nsample', 100)->nullable();
                $table->string('isample', 100)->nullable();
                $table->integer('paid')->nullable();
                $table->string('had', 100)->nullable();
                $table->string('sender', 100)->nullable();
                $table->integer('pleft')->nullable();
                $table->integer('total')->nullable();
                $table->string('lab')->unique()->nullable();
                $table->string('entryday', 20)->nullable();
                $table->string('deliday', 20)->nullable();
                $table->string('gender', 30)->nullable();
                $table->string('type', 50)->nullable();
                $table->string('doctor_id')->nullable();
                $table->string('organization_id')->nullable();
                $table->string('sample_type')->nullable();
                $table->string('case_type')->nullable();
                $table->string('sample_size')->nullable();
                $table->integer('number_of_samples')->nullable();
                $table->string('day_of_week')->nullable();
                $table->text('previous_tests')->nullable();
                $table->date('attendance_date')->nullable();
                $table->date('delivery_date')->nullable();
                $table->decimal('total_amount', 10, 2)->nullable();
                $table->decimal('amount_paid', 10, 2)->nullable();
                $table->text('medical_history')->nullable();
                $table->text('allergies')->nullable();
                $table->string('emergency_contact')->nullable();
                $table->string('emergency_phone')->nullable();
                $table->date('birth_date')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->timestamps();
            });
        }

        // Create shifts table (needed before visits)
        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('users');
                $table->string('shift_type')->default('AM');
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->decimal('total_collected', 10, 2)->default(0.00);
                $table->integer('patients_served')->default(0);
                $table->integer('visits_processed')->default(0);
                $table->integer('payments_processed')->default(0);
                $table->text('notes')->nullable();
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->timestamps();
            });
        }

        // Create lab_requests table
        if (!Schema::hasTable('lab_requests')) {
            Schema::create('lab_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->nullable()->constrained('patient');
                $table->string('lab_no', 100);
                $table->enum('suffix', ['m', 'h'])->nullable();
                $table->enum('status', ['pending', 'received', 'in_progress', 'under_review', 'completed', 'delivered'])->default('pending');
                $table->longText('metadata')->nullable();
                $table->timestamps();
                
                $table->index('lab_no');
                $table->index('status');
            });
        }

        // Create visits table
        if (!Schema::hasTable('visits')) {
            Schema::create('visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patient');
                $table->string('visit_number')->unique();
                $table->date('visit_date');
                $table->time('visit_time');
                $table->decimal('total_amount', 10, 2);
                $table->decimal('discount_amount', 10, 2)->default(0.00);
                $table->decimal('final_amount', 10, 2);
                $table->string('status')->default('pending');
                $table->longText('checked_by_doctors')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('lab_request_id')->nullable()->constrained('lab_requests');
                $table->foreignId('shift_id')->nullable()->constrained('shifts');
                $table->foreignId('processed_by_staff')->nullable()->constrained('users');
                $table->timestamps();
                
                $table->index('visit_date');
                $table->index('created_at');
            });
        }

        // Create visit_tests table
        if (!Schema::hasTable('visit_tests')) {
            Schema::create('visit_tests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('visit_id')->constrained('visits');
                $table->foreignId('lab_test_id')->constrained('lab_tests');
                $table->decimal('price', 10, 2);
                $table->enum('status', ['pending', 'under_review', 'completed'])->default('pending');
                $table->string('barcode_uid')->unique();
                $table->string('result_value')->nullable();
                $table->string('result_status')->nullable();
                $table->text('result_notes')->nullable();
                $table->foreignId('performed_by')->nullable()->constrained('users');
                $table->timestamp('performed_at')->nullable();
                $table->timestamps();
                
                $table->index('status');
            });
        }

        // Create invoices table
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('lab', 256);
                $table->integer('total');
                $table->integer('paid');
                $table->integer('remaining');
                $table->foreignId('lab_request_id')->nullable()->constrained('lab_requests');
                $table->foreignId('shift_id')->nullable()->constrained('shifts');
                
                $table->index('remaining');
            });
        }

        // Create payments table
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->integer('paid')->default(0);
                $table->string('comment')->nullable();
                $table->date('date')->nullable();
                $table->integer('author');
                $table->integer('income')->default(0);
                $table->foreignId('invoice_id')->nullable()->constrained('invoices');
                $table->foreignId('shift_id')->nullable()->constrained('shifts');
            });
        }

        // Create samples table
        if (!Schema::hasTable('samples')) {
            Schema::create('samples', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lab_request_id')->constrained('lab_requests');
                $table->string('sample_type');
                $table->string('case_type')->nullable();
                $table->string('sample_size')->nullable();
                $table->integer('number_of_samples')->nullable();
                $table->string('sample_id');
                $table->datetime('collection_date')->nullable();
                $table->datetime('received_date')->nullable();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('analysis_started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('disposed_at')->nullable();
                $table->enum('status', ['collected', 'received', 'processing', 'analyzing', 'completed', 'disposed', 'lost', 'rejected'])->default('collected');
                $table->text('notes')->nullable();
                $table->foreignId('collected_by')->nullable()->constrained('users');
                $table->foreignId('received_by')->nullable()->constrained('users');
                $table->foreignId('processed_by')->nullable()->constrained('users');
                $table->foreignId('analyzed_by')->nullable()->constrained('users');
                $table->foreignId('disposed_by')->nullable()->constrained('users');
                $table->string('location')->nullable();
                $table->timestamps();
                
                $table->index('status');
            });
        }

        // Create reports table
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lab_request_id')->nullable()->constrained('lab_requests');
                $table->string('title');
                $table->text('content');
                $table->string('image_path')->nullable();
                $table->string('image_filename')->nullable();
                $table->string('image_mime_type')->nullable();
                $table->bigInteger('image_size')->nullable();
                $table->timestamp('image_uploaded_at')->nullable();
                $table->foreignId('image_uploaded_by')->nullable()->constrained('users');
                $table->string('status')->default('draft');
                $table->foreignId('generated_by')->nullable()->constrained('users');
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                
                $table->index('generated_at');
            });
        }

        // Create enhanced_reports table
        if (!Schema::hasTable('enhanced_reports')) {
            Schema::create('enhanced_reports', function (Blueprint $table) {
                $table->id();
                $table->text('nos');
                $table->text('reff');
                $table->text('clinical')->nullable();
                $table->text('nature')->nullable();
                $table->date('report_date')->nullable();
                $table->string('lab_no')->nullable();
                $table->string('age')->nullable();
                $table->text('gross')->nullable();
                $table->text('micro')->nullable();
                $table->text('conc')->nullable();
                $table->text('reco')->nullable();
                $table->string('type')->nullable();
                $table->string('sex')->nullable();
                $table->string('recieving')->nullable();
                $table->string('discharge')->nullable();
                $table->boolean('confirm')->default(false);
                $table->boolean('print')->default(false);
                $table->foreignId('patient_id')->nullable()->constrained('patient');
                $table->foreignId('lab_request_id')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('reviewed_by')->nullable();
                $table->foreignId('approved_by')->nullable();
                $table->enum('status', ['draft', 'under_review', 'approved', 'printed', 'delivered'])->default('draft');
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
                $table->longText('examination_details')->nullable();
                $table->longText('quality_control')->nullable();
                $table->string('barcode')->nullable();
                $table->string('digital_signature')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('printed_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
                
                $table->index('patient_id');
            });
        }

        // Create templates table
        if (!Schema::hasTable('templates')) {
            Schema::create('templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('clinical_data')->nullable();
                $table->text('microscopic')->nullable();
                $table->text('diagnosis')->nullable();
                $table->text('recommendations')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
            });
        }

        // Create patient_credentials table
        if (!Schema::hasTable('patient_credentials')) {
            Schema::create('patient_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patient');
                $table->string('username')->unique();
                $table->string('original_password');
                $table->string('hashed_password');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create expenses table
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->string('description');
                $table->decimal('amount', 10, 2);
                $table->string('category', 100);
                $table->date('expense_date');
                $table->string('payment_method', 50)->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
            });
        }

        // Create inventory_items table
        if (!Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('unit');
                $table->integer('quantity');
                $table->integer('minimum_quantity')->default(0);
                $table->decimal('unit_price', 10, 2)->nullable();
                $table->string('supplier')->nullable();
                $table->date('expiry_date')->nullable();
                $table->enum('status', ['active', 'low_stock', 'out_of_stock', 'expired'])->default('active');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }

        // Create lab_sequences table
        if (!Schema::hasTable('lab_sequences')) {
            Schema::create('lab_sequences', function (Blueprint $table) {
                $table->id();
                $table->integer('year')->unique();
                $table->integer('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        // Create cache tables
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraints
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('lab_sequences');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('patient_credentials');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('enhanced_reports');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('visit_tests');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('lab_requests');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('patient');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('test_categories');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
    }
};
