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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Company/Supplier name
            $table->text('description')->nullable(); // Optional description
            $table->decimal('total_amount', 10, 2)->default(0); // Total amount owed
            $table->decimal('total_paid', 10, 2)->default(0); // Total amount paid
            $table->decimal('remaining_balance', 10, 2)->default(0); // Remaining balance
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
