<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_panels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Panel price (may be different from sum of individual tests)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_panel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_panel_id')->constrained('test_panels')->onDelete('cascade');
            $table->foreignId('lab_test_id')->constrained('lab_tests')->onDelete('cascade');
            $table->integer('sort_order')->default(0); // Order within panel
            $table->boolean('is_required')->default(true); // Whether test is required in panel
            $table->timestamps();
            
            $table->unique(['test_panel_id', 'lab_test_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_panel_items');
        Schema::dropIfExists('test_panels');
    }
}; 