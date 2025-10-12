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
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->date('transaction_date'); // Date of the transaction
            $table->decimal('amount', 10, 2); // Amount for this transaction
            $table->decimal('paid_amount', 10, 2)->default(0); // Amount paid in this transaction
            $table->decimal('remaining_amount', 10, 2); // Remaining amount after this transaction
            $table->enum('type', ['purchase', 'payment']); // Type: purchase (add to debt) or payment (reduce debt)
            $table->text('description')->nullable(); // Description of the transaction
            $table->text('notes')->nullable(); // Additional notes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
