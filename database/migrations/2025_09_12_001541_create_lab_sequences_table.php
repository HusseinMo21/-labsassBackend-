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
        if (!Schema::hasTable('lab_sequences')) {
            Schema::create('lab_sequences', function (Blueprint $table) {
                $table->id();
                $table->year('year')->unique();
                $table->unsignedInteger('last_sequence')->default(6999);
                $table->timestamps();

                // Index for performance
                $table->index('year');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_sequences');
    }
};
