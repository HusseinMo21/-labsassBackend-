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
        Schema::create('report_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->onDelete('cascade');
            $table->integer('top_margin')->default(60)->comment('Top margin in pixels (5-40)');
            $table->integer('bottom_margin')->default(120)->comment('Bottom margin in pixels (5-40)');
            $table->integer('left_margin')->default(40)->comment('Left margin in pixels (5-40)');
            $table->integer('right_margin')->default(40)->comment('Right margin in pixels (5-40)');
            $table->integer('content_padding')->default(10)->comment('Content padding in pixels (5-40)');
            $table->timestamps();
            
            // Ensure one settings record per visit
            $table->unique('visit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_settings');
    }
};
