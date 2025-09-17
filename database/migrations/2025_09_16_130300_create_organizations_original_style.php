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
        Schema::create('organizations', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 255);
            $table->string('type', 100)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
