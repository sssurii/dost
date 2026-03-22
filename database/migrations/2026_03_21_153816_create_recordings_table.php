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
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('mime_type')->default('audio/mp4');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();

            $table->string('status')->default('pending');

            $table->text('transcript')->nullable();
            $table->text('ai_response_text')->nullable();
            $table->string('ai_response_audio_path')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
