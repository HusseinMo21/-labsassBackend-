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
        Schema::table('visits', function (Blueprint $table) {
            // Image support fields
            $table->string('image_path')->nullable()->comment('Path to lab result image');
            $table->string('image_filename')->nullable()->comment('Original image filename');
            $table->string('image_mime_type')->nullable()->comment('Image MIME type');
            $table->bigInteger('image_size')->nullable()->comment('Image file size in bytes');
            $table->timestamp('image_uploaded_at')->nullable()->comment('When image was uploaded');
            $table->foreignId('image_uploaded_by')->nullable()->constrained('users')->onDelete('set null')->comment('Who uploaded the image');
            
            // Index for image queries
            $table->index('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['image_path']);
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