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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public')->comment('Storage disk name');
            $table->string('directory')->nullable()->comment('Upload directory');
            $table->string('category')->comment('File category: images, documents, audio, video, archives');
            $table->string('name')->comment('Original filename for display');
            $table->string('filename')->unique()->comment('Stored filename (UUID)');
            $table->string('mime_type')->comment('MIME type of the file');
            $table->string('extension')->comment('File extension');
            $table->bigInteger('size')->comment('File size in bytes');
            $table->string('original_path')->comment('Path to the original file');
            $table->json('thumbnails')->nullable()->comment('JSON array of thumbnail paths');
            $table->foreignId('user_id')->nullable()->index();
            // Polymorphic relationship
            $table->string('mediable_type')->nullable();
            $table->unsignedBigInteger('mediable_id')->nullable();
            $table->index(['mediable_type', 'mediable_id']);

            $table->timestamps();

            // Indexes for common queries
            $table->index('category');
            // $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
