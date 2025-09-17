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
        // Create temporary tables to hold legacy data during migration
        if (!Schema::hasTable('legacy_patients')) {
            Schema::create('legacy_patients', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('name', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('entry', 100)->nullable();
                $table->string('deli', 100)->nullable();
                $table->string('time', 255)->nullable();
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
                $table->string('lab', 100)->nullable();
                $table->string('entryday', 20)->nullable();
                $table->string('deliday', 20)->nullable();
                $table->string('gender', 30)->nullable();
                $table->string('type', 50)->nullable();
            });
        }

        if (!Schema::hasTable('legacy_pathology')) {
            Schema::create('legacy_pathology', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->mediumText('nos')->nullable();
                $table->string('reff', 255)->nullable();
                $table->mediumText('clinical')->nullable();
                $table->mediumText('nature')->nullable();
                $table->string('date', 50)->nullable();
                $table->string('lab', 50)->nullable();
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

        if (!Schema::hasTable('legacy_income')) {
            Schema::create('legacy_income', function (Blueprint $table) {
                $table->integer('ID')->primary();
                $table->string('patient', 256);
                $table->string('name', 256);
                $table->integer('total');
                $table->integer('paid');
                $table->integer('due');
                $table->date('date');
                $table->integer('Author');
            });
        }

        if (!Schema::hasTable('legacy_expenses')) {
            Schema::create('legacy_expenses', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('name', 256);
                $table->integer('amount');
                $table->date('date');
                $table->integer('author');
            });
        }

        if (!Schema::hasTable('legacy_login')) {
            Schema::create('legacy_login', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('username', 255)->nullable();
                $table->string('password', 255)->nullable();
                $table->string('permssion', 11)->default('0');
                $table->string('name', 100)->nullable();
                $table->string('users', 20)->nullable();
            });
        }

        if (!Schema::hasTable('legacy_invoices')) {
            Schema::create('legacy_invoices', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('lab', 256);
                $table->integer('total');
                $table->integer('paid');
                $table->integer('remaining');
            });
        }

        if (!Schema::hasTable('legacy_payments')) {
            Schema::create('legacy_payments', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->integer('paid')->default(0);
                $table->string('comment', 255)->nullable();
                $table->date('date')->nullable();
                $table->integer('author');
                $table->integer('income')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_patients');
        Schema::dropIfExists('legacy_pathology');
        Schema::dropIfExists('legacy_income');
        Schema::dropIfExists('legacy_expenses');
        Schema::dropIfExists('legacy_login');
        Schema::dropIfExists('legacy_invoices');
        Schema::dropIfExists('legacy_payments');
    }
};
