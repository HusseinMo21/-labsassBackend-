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
        Schema::create('patholgy', function (Blueprint $table) {
            $table->integer('id', true);
            $table->mediumText('nos')->nullable();
            $table->string('reff')->nullable();
            $table->mediumText('clinical')->nullable();
            $table->mediumText('nature')->nullable();
            $table->string('date', 50)->nullable();
            $table->string('lab', 50)->nullable()->unique('lab');
            $table->string('age', 30)->nullable();
            $table->mediumText('gross')->nullable();
            $table->mediumText('micro')->nullable();
            $table->mediumText('conc')->nullable();
            $table->mediumText('reco')->nullable();
            $table->string('type', 25)->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('recieving', 100)->nullable();
            $table->string('discharge', 100)->nullable();
            $table->integer('confirm')->default(0);
            $table->integer('print')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patholgy');
    }
};
