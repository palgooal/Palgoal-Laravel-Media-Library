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
        if (Schema::hasTable('media')) {
            return;
        }

        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Internal stored file name (usually hashed)
            $table->string('file_name');

            // Original file name as uploaded by the user
            $table->string('file_original_name')->nullable();

            // Relative path inside storage (e.g. media/2025/06/file.jpg)
            $table->string('file_path');

            // Basic technical info
            $table->string('file_extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0); // in bytes

            // Optional classification (image, video, audio, document, other)
            $table->string('file_type', 50)->nullable();

            // Disk name (public, s3, etc.)
            $table->string('disk', 50)->default('public');

            // Optional image dimensions
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Ownership / uploader
            $table->unsignedBigInteger('uploader_id')->nullable()->index();

            // SEO / content fields
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
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
