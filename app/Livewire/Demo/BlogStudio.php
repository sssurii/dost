<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use App\Enums\BlogPostStatus;
use App\Jobs\Demo\GenerateBlogImage;
use App\Jobs\Demo\GenerateBlogText;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.demo')]
final class BlogStudio extends Component
{
    public string $topic = '';

    /** UUID that doubles as the BlogPost primary key and broadcast channel ID. */
    public string $blogPostId = '';

    public string $title = '';

    public string $content = '';

    public string $summary = '';

    public string $imageUrl = '';

    public string $audioUrl = '';

    /** @var array<int, array{provider: string, status: string, message: string}> */
    public array $failoverLog = [];

    public bool $isGenerating = false;

    /** @var string[] */
    public array $completedSteps = [];

    public string $error = '';

    public function mount(): void
    {
        $this->blogPostId = Str::uuid()->toString();
    }

    public function generate(): void
    {
        $this->reset('title', 'content', 'summary', 'imageUrl', 'audioUrl', 'failoverLog', 'completedSteps', 'error');

        if (blank($this->topic)) {
            $this->error = 'Please enter a blog topic.';

            return;
        }

        $this->isGenerating = true;

        $this->createOrResetBlogPost();

        GenerateBlogText::dispatch($this->blogPostId, $this->topic);
        GenerateBlogImage::dispatch($this->blogPostId, $this->topic);
    }

    /**
     * Create the BlogPost DB record (or reset an existing one) before dispatching jobs.
     *
     * We explicitly assign $blogPostId as the primary key because it was pre-generated
     * in mount() and the browser's Echo listener is already subscribed to
     * `demo.studio.{blogPostId}`. Letting HasUuids auto-generate a different UUID would
     * break the broadcast channel linkage.
     */
    private function createOrResetBlogPost(): void
    {
        $blogPost = BlogPost::withTrashed()->find($this->blogPostId);

        if ($blogPost) {
            if ($blogPost->trashed()) {
                $blogPost->restore();
            }

            $blogPost->update([
                'topic' => $this->topic,
                'status' => BlogPostStatus::Generating,
                'title' => null,
                'slug' => null,
                'summary' => null,
                'content' => null,
                'image_path' => null,
                'audio_path' => null,
                'word_count' => null,
                'audio_failover_log' => null,
                'published_at' => null,
            ]);

            return;
        }

        // New record: set id directly so HasUuids does not generate a different UUID.
        // The id must match $this->blogPostId, which the Echo channel was registered with at mount.
        $blogPost = new BlogPost(['topic' => $this->topic, 'status' => BlogPostStatus::Generating]);
        $blogPost->id = $this->blogPostId;
        $blogPost->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[On('echo:demo.studio.{blogPostId},.studio.result')]
    public function onResult(array $data): void
    {
        $type = $data['type'] ?? '';

        match ($type) {
            'text' => $this->handleText($data['payload'] ?? []),
            'image' => $this->handleImage($data['payload'] ?? []),
            'audio' => $this->handleAudio($data['payload'] ?? [], $data['failoverLog'] ?? []),
            default => null,
        };

        if (! in_array($type, $this->completedSteps, true) && $type !== '') {
            $this->completedSteps[] = $type;
        }

        if (count($this->completedSteps) >= 3) {
            $this->isGenerating = false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleText(array $payload): void
    {
        $this->title = $payload['title'] ?? '';
        $this->content = $payload['content'] ?? '';
        $this->summary = $payload['summary'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleImage(array $payload): void
    {
        $this->imageUrl = $payload['url'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{provider: string, status: string, message: string}>  $failoverLog
     */
    private function handleAudio(array $payload, array $failoverLog): void
    {
        $this->audioUrl = $payload['url'] ?? '';
        $this->failoverLog = $failoverLog;
    }

    public function render(): View
    {
        return view('livewire.demo.blog-studio')
            ->title('Blog Studio');
    }
}
