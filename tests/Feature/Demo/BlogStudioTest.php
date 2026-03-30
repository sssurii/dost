<?php

use App\Enums\BlogPostStatus;
use App\Events\BlogStudioResultReady;
use App\Jobs\Demo\GenerateBlogImage;
use App\Jobs\Demo\GenerateBlogText;
use App\Livewire\Demo\BlogStudio;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the blog studio page', function () {
    $this->get('/demo/studio')->assertStatus(200);
});

it('requires a topic to generate', function () {
    Livewire::test(BlogStudio::class)
        ->set('topic', '')
        ->call('generate')
        ->assertSet('error', 'Please enter a blog topic.')
        ->assertSet('isGenerating', false);
});

it('dispatches text and image jobs on generate', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'AI in Healthcare')
        ->call('generate');

    Queue::assertPushed(GenerateBlogText::class, fn ($job) => $job->topic === 'AI in Healthcare');
    Queue::assertPushed(GenerateBlogImage::class, fn ($job) => $job->topic === 'AI in Healthcare');
});

it('creates a blog post record with generating status on generate', function () {
    Queue::fake();

    $component = Livewire::test(BlogStudio::class)
        ->set('topic', 'Test Topic')
        ->call('generate');

    $blogPostId = $component->get('blogPostId');

    $post = BlogPost::find($blogPostId);
    expect($post)->not->toBeNull()
        ->and($post->topic)->toBe('Test Topic')
        ->and($post->status)->toBe(BlogPostStatus::Generating);
});

it('resets the blog post record on re-generate', function () {
    Queue::fake();

    $component = Livewire::test(BlogStudio::class)
        ->set('topic', 'First Topic')
        ->call('generate');

    $blogPostId = $component->get('blogPostId');
    BlogPost::where('id', $blogPostId)->update(['title' => 'Old Title']);

    $component->set('topic', 'Second Topic')->call('generate');

    $post = BlogPost::find($blogPostId);
    expect($post->title)->toBeNull()
        ->and($post->topic)->toBe('Second Topic')
        ->and($post->status)->toBe(BlogPostStatus::Generating);
});

it('sets isGenerating flag on generate', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'Test Topic')
        ->call('generate')
        ->assertSet('isGenerating', true);
});

it('handles text result via onResult', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'Test')
        ->call('generate')
        ->call('onResult', [
            'type' => 'text',
            'payload' => ['title' => 'Test Title', 'content' => 'Test content body.', 'summary' => 'A test summary.'],
            'failoverLog' => [],
        ])
        ->assertSet('title', 'Test Title')
        ->assertSet('content', 'Test content body.')
        ->assertSet('summary', 'A test summary.');
});

it('handles image result via onResult', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'Test')
        ->call('generate')
        ->call('onResult', [
            'type' => 'image',
            'payload' => ['url' => 'https://example.com/image.png'],
            'failoverLog' => [],
        ])
        ->assertSet('imageUrl', 'https://example.com/image.png');
});

it('handles audio result with failover log', function () {
    Queue::fake();

    $failoverLog = [
        ['provider' => 'Gemini', 'status' => 'failed',  'message' => 'AudioProvider not supported'],
        ['provider' => 'OpenAI', 'status' => 'success', 'message' => 'Audio generated successfully.'],
    ];

    Livewire::test(BlogStudio::class)
        ->set('topic', 'Test')
        ->call('generate')
        ->call('onResult', [
            'type' => 'audio',
            'payload' => ['url' => 'https://example.com/audio.mp3'],
            'failoverLog' => $failoverLog,
        ])
        ->assertSet('audioUrl', 'https://example.com/audio.mp3')
        ->assertSet('failoverLog', $failoverLog);
});

it('clears isGenerating when all 3 steps complete', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'Test')
        ->call('generate')
        ->call('onResult', ['type' => 'text',  'payload' => ['title' => 'T', 'content' => 'C', 'summary' => 'S'], 'failoverLog' => []])
        ->call('onResult', ['type' => 'image', 'payload' => ['url' => 'http://img'], 'failoverLog' => []])
        ->call('onResult', ['type' => 'audio', 'payload' => ['url' => 'http://aud'], 'failoverLog' => []])
        ->assertSet('isGenerating', false)
        ->assertSet('completedSteps', ['text', 'image', 'audio']);
});

it('broadcasts BlogStudioResultReady event with blogPostId', function () {
    Event::fake();

    BlogStudioResultReady::dispatch('blog-post-uuid', 'text', [
        'title' => 'Test Title',
        'content' => 'Test body',
        'summary' => 'Test summary',
    ]);

    Event::assertDispatched(
        BlogStudioResultReady::class,
        fn ($e) => $e->blogPostId === 'blog-post-uuid' && $e->type === 'text',
    );
});

it('resets state on new generate', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'First')
        ->call('generate')
        ->call('onResult', ['type' => 'text', 'payload' => ['title' => 'Old', 'content' => 'Old', 'summary' => 'Old'], 'failoverLog' => []])
        ->set('topic', 'Second')
        ->call('generate')
        ->assertSet('title', '')
        ->assertSet('content', '')
        ->assertSet('completedSteps', []);
});

it('BlogPost model generates imageUrl through Storage disk', function () {
    $post = BlogPost::factory()->withImage()->create();

    expect($post->image_url)->toContain('/storage/demo/images/');
});

it('BlogPost model generates audioUrl through Storage disk', function () {
    $post = BlogPost::factory()->withAudio()->create();

    expect($post->audio_url)->toContain('/storage/demo/audio/');
});

it('BlogPost model computes reading time from word count', function () {
    $post = BlogPost::factory()->create(['word_count' => 400]);

    expect($post->reading_time_minutes)->toBe(2);
});

it('BlogPost::makeSlug produces a URL-safe slug with UUID suffix', function () {
    $slug = BlogPost::makeSlug('Hello World', 'abcd1234-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

    expect($slug)->toBe('hello-world-abcd1234');
});

it('BlogPost publish() sets status and published_at', function () {
    $post = BlogPost::factory()->create();
    $post->publish();

    expect($post->fresh()->status)->toBe(BlogPostStatus::Published)
        ->and($post->fresh()->published_at)->not->toBeNull();
});

it('BlogPost scopePublished only returns published posts', function () {
    BlogPost::factory()->create();
    BlogPost::factory()->published()->create();
    BlogPost::factory()->archived()->create();

    expect(BlogPost::published()->count())->toBe(1);
});
