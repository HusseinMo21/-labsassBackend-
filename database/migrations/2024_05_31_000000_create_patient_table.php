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
        Schema::create('patient', function (Blueprint $table) {
            // Modern fields that frontend expects
            $table->id();
            $table->string('name')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->string('address_required')->nullable();
            $table->string('address_optional')->nullable();
            $table->string('organization')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('doctor_id')->nullable(); // Store doctor name as string
            $table->string('organization_id')->nullable(); // Store organization name as string
            $table->timestamps();
            
            // Legacy fields for backward compatibility
            $table->string('entry', 100)->nullable();
            $table->string('deli', 100)->nullable();
            $table->string('time')->nullable();
            $table->integer('age')->nullable();
            $table->string('tsample', 100)->nullable();
            $table->string('nsample', 100)->nullable();
            $table->string('isample', 100)->nullable();
            $table->integer('paid')->nullable();
            $table->string('had', 100)->nullable();
            $table->string('sender', 100)->nullable();
            $table->integer('pleft')->nullable();
            $table->integer('total')->nullable();
            $table->string('lab')->nullable()->unique('patient_lab_unique');
            $table->string('entryday', 20)->nullable();
            $table->string('deliday', 20)->nullable();
            $table->string('type', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient');
    }
};
