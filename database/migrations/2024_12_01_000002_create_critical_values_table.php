<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->onDelete('cascade');
            $table->decimal('critical_low', 10, 2)->nullable(); // Critical low threshold
            $table->decimal('critical_high', 10, 2)->nullable(); // Critical high threshold
            $table->string('unit')->nullable(); // Unit of measurement
            $table->text('notification_message')->nullable(); // Custom notification message
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_values');
    }
}; 