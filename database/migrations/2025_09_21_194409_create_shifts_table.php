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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->string('shift_type')->default('AM'); // AM, PM, Night
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('total_collected', 10, 2)->default(0);
            $table->integer('patients_served')->default(0);
            $table->integer('visits_processed')->default(0);
            $table->integer('payments_processed')->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
