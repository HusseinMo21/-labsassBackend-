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
        Schema::table('reports', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->string('image_filename')->nullable();
            $table->string('image_mime_type')->nullable();
            $table->bigInteger('image_size')->nullable();
            $table->timestamp('image_uploaded_at')->nullable();
            $table->unsignedBigInteger('image_uploaded_by')->nullable();
            
            $table->foreign('image_uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['image_uploaded_by']);
            $table->dropColumn([
                'image_path',
                'image_filename', 
                'image_mime_type',
                'image_size',
                'image_uploaded_at',
                'image_uploaded_by'
            ]);
        });
    }
};
