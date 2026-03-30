<?php

declare(strict_types=1);

namespace App\Jobs\Demo;

use App\Ai\Agents\Demo\BlogTextAgent;
use App\Enums\BlogPostStatus;
use App\Events\BlogStudioResultReady;
use App\Models\BlogPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateBlogText implements ShouldQueue
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
        $response = BlogTextAgent::make()->prompt(
            "Write a blog post about: {$this->topic}",
        );

        $title = $response['title'] ?? '';
        $content = $response['content'] ?? '';
        $summary = $response['summary'] ?? '';

        BlogPost::where('id', $this->blogPostId)->update([
            'title' => $title,
            'slug' => BlogPost::makeSlug($title, $this->blogPostId),
            'summary' => $summary,
            'content' => $content,
            'word_count' => str_word_count(strip_tags($content)),
            'status' => BlogPostStatus::Draft,
        ]);

        BlogStudioResultReady::dispatch($this->blogPostId, 'text', [
            'title' => $title,
            'content' => $content,
            'summary' => $summary,
        ]);

        GenerateBlogAudio::dispatch($this->blogPostId, $content);
    }
}
