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
            $table->integer('id', true);
            $table->string('name', 150)->nullable();
            $table->string('address')->nullable();
            $table->string('entry', 100)->nullable();
            $table->string('deli', 100)->nullable();
            $table->string('time')->nullable();
            $table->integer('age')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('tsample', 100)->nullable();
            $table->string('nsample', 100)->nullable();
            $table->string('isample', 100)->nullable();
            $table->integer('paid')->nullable();
            $table->string('had', 100)->nullable();
            $table->string('sender', 100)->nullable();
            $table->integer('pleft')->nullable();
            $table->integer('total')->nullable();
            $table->string('lab')->nullable()->unique('lab');
            $table->string('entryday', 20)->nullable();
            $table->string('deliday', 20)->nullable();
            $table->string('gender', 30)->nullable();
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
