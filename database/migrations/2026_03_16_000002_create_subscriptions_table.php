<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('labs')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->enum('status', ['active', 'expired', 'cancelled', 'trial'])->default('active');
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->decimal('amount', 12, 2)->default(0)->comment('Plan price at subscription time');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
