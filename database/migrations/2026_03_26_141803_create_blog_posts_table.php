<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // User input
            $table->string('topic');

            // AI-generated content
            $table->string('title')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();

            // Media — storage-relative paths, never full URLs
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();

            // Computed metadata
            $table->unsignedInteger('word_count')->nullable();

            // Lifecycle
            $table->string('status')->default('generating');
            $table->jsonb('audio_failover_log')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
