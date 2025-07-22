<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // 'create', 'update', 'delete', 'view', 'login', 'logout'
            $table->string('model_type'); // 'Patient', 'VisitTest', 'LabTest', etc.
            $table->unsignedBigInteger('model_id')->nullable(); // ID of the affected record
            $table->string('table_name'); // Database table name
            $table->json('old_values')->nullable(); // Previous values (for updates/deletes)
            $table->json('new_values')->nullable(); // New values (for creates/updates)
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable(); // Human-readable description
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
}; 