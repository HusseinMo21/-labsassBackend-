<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_test_id')->nullable()->constrained('visit_tests')->onDelete('cascade');
            $table->foreignId('patient_id')->nullable()->constrained('patient')->onDelete('cascade');
            $table->string('type'); // 'sms', 'email', 'critical_alert'
            $table->string('recipient_type'); // 'patient', 'doctor', 'lab_staff'
            $table->string('recipient_contact'); // phone or email
            $table->text('message');
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional data like SMS provider response
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
}; 