<?php

declare(strict_types=1);

namespace App\Jobs\Demo;

use App\Events\BlogStudioResultReady;
use App\Models\BlogPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

final class GenerateBlogImage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(
        public string $blogPostId,
        public string $topic,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $response = Image::of(
            "Professional editorial blog featured image for an article about: {$this->topic}. Modern, clean, photographic style.",
        )->landscape()->generate('gemini');

        $path = (string) $response->storePublicly('demo/images', 'public');

        BlogPost::where('id', $this->blogPostId)->update([
            'image_path' => $path,
        ]);

        BlogStudioResultReady::dispatch($this->blogPostId, 'image', [
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
