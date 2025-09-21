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
            $table->string('image_path')->nullable()->after('content');
            $table->string('image_filename')->nullable()->after('image_path');
            $table->string('image_mime_type')->nullable()->after('image_filename');
            $table->bigInteger('image_size')->nullable()->after('image_mime_type');
            $table->timestamp('image_uploaded_at')->nullable()->after('image_size');
            $table->unsignedBigInteger('image_uploaded_by')->nullable()->after('image_uploaded_at');
            
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
