# DEMO-02: Blog Studio — Unified AI SDK Demo

**Purpose:** Single-page demo showcasing ALL `laravel/ai` capabilities in one flow
**Phase:** Standalone (presentation demo)
**Estimate:** ~3–4h
**Status:** 🔲 Not started
**Date:** March 26, 2026
**Depends on:** DEMO-01 (layout, routes, config already in place)

---

## 1. Objective

One page at `/demo/studio` where the user enters a blog topic and 4 AI tasks execute via **queued jobs**, broadcasting results back to the UI in **real-time via Reverb**:

1. **Blog Text** → structured output with `title` + `content` + `summary` (Gemini, `HasStructuredOutput`)
2. **Featured Image** → image generation (Gemini)
3. **Audio Narration** → TTS of **full article** with **failover** (Gemini fails → OpenAI succeeds)

Results arrive progressively via Reverb and render into a **polished blog post view**:

```
┌─────────────────────────────────────────────────┐
│  [Generated Title]                              │
│  🎧 Audio Player (full narration)               │
│  [Featured Image — full width]                  │
│  [Article Content — rendered markdown]          │
│                                                 │
│  Failover Log: ❌ Gemini → ✅ OpenAI           │
│                                                 │
│  📋 How to Test This (collapsible)              │
└─────────────────────────────────────────────────┘
```

### PPT Slides Covered (all in one flow)

| Slide | Feature | How it's shown |
|-------|---------|----------------|
| 2 | Core Integration | Structured agent with `#[Provider]` attribute |
| 3 | Multimodal | Text + Image generated from same topic |
| 4 | Audio Intelligence | TTS narration of full article content |
| 5 | Knowledge & Search | *(not covered — keep separate demo)* |
| 6 | Autonomous Logic | Queued jobs with event-driven pipeline + structured output |
| 7 | Enterprise Resiliency | Audio failover: Gemini → OpenAI |

---

## 2. Architecture

```
User enters topic → clicks "Generate Blog"
        │
        ▼
BlogStudio Livewire component
   ├── dispatch(GenerateBlogText::class)     ─── ai queue (structured output)
   ├── dispatch(GenerateBlogImage::class)    ─── ai queue
   └── (text completes →)
        └── dispatch(GenerateBlogAudio::class)  ─── ai queue (chained)
                │
                ▼  Audio failover:
                │  Gemini (fails — no AudioProvider) → OpenAI (succeeds)
                │  SDK fires ProviderFailedOver event
                │
    ┌───────────┘
    ▼
BlogStudioResultReady broadcast event (per-task)
    │  type: 'text' | 'image' | 'audio'
    │  channel: demo.studio.{sessionId} (public)
    ▼
Livewire #[On('echo:demo.studio.{sessionId},studio.result')]
    → UI progressively builds the blog post view
```

### Job flow

- **GenerateBlogText** and **GenerateBlogImage** dispatch in **parallel**
- **GenerateBlogText** completes → broadcasts `text` result (title + content + summary) → then dispatches **GenerateBlogAudio** with the full article content
- **GenerateBlogAudio** narrates the **full article** (not truncated) → failover from Gemini to OpenAI

### Why Chained Audio?

Audio narrates the generated **article content**. It can't run in parallel because it needs the text. Solution: text + image run in parallel; audio chains after text. This also demos **job chaining** — another Laravel feature worth showing.

---

## 3. Prerequisite: Laravel Echo + Reverb Frontend

Echo is NOT yet installed on the frontend. Reverb is configured server-side but `bootstrap.js` has no Echo setup. **Decision: install as part of this task.**

### Install steps:
```bash
./bin/npm install laravel-echo pusher-js
```

### Wire up in `resources/js/bootstrap.js`:
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### Ensure `.env` has:
```
BROADCAST_CONNECTION=reverb
VITE_REVERB_APP_KEY=...
VITE_REVERB_HOST=...
VITE_REVERB_PORT=...
VITE_REVERB_SCHEME=http
```

**Note:** This unblocks ALL Reverb features (including existing Dost voice pipeline `AiResponseReady` event). One-time setup.

---

## 4. SDK API Reference (for this demo)

### Structured Agent (BlogTextAgent) — `HasStructuredOutput`
```php
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
final class BlogTextAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a professional blog writer. Given a topic, write a complete blog post.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Catchy, SEO-friendly blog title.')
                ->required(),
            'content' => $schema->string()
                ->description('Full 3-4 paragraph blog post in markdown format.')
                ->required(),
            'summary' => $schema->string()
                ->description('One-sentence summary of the article for meta description.')
                ->required(),
        ];
    }
}
```

**Usage:**
```php
$response = BlogTextAgent::make()->prompt("Write a blog post about: $topic");
// $response is StructuredAgentResponse (ArrayAccess)
$response['title'];   // "The Future of AI in Healthcare"
$response['content']; // "## Introduction\nAI is transforming..."
$response['summary']; // "A deep dive into how AI is reshaping modern healthcare."
```

### Image via Image::of()
```php
use Laravel\Ai\Image;

$response = Image::of("Blog image for: $topic")->landscape()->generate('gemini');
$path = $response->storePublicly('demo/images', 'public'); // returns relative path
```

### Audio with Failover (full article narration)
```php
use Laravel\Ai\Audio;

// Gemini doesn't implement AudioProvider → FailoverableException → falls over to OpenAI
$response = Audio::of($fullArticleContent)
    ->voice('nova')
    ->generate(['gemini' => 'gemini-2.5-flash', 'openai' => 'tts-1']);
$path = $response->storePublicly('demo/audio', 'public');
```

### ProviderFailedOver Event (SDK auto-fires)
```php
use Laravel\Ai\Events\ProviderFailedOver;

Event::listen(ProviderFailedOver::class, function (ProviderFailedOver $event) {
    // $event->provider (Provider instance)
    // $event->model (string)
    // $event->exception (FailoverableException)
});
```

### Testing Helpers
```php
use Laravel\Ai\Audio;
use Laravel\Ai\Image;

// Fake everything
Audio::fake();
Image::fake();
BlogTextAgent::fake();

// Assert structured generation
BlogTextAgent::assertPrompted(fn ($p) => str_contains($p->prompt, 'healthcare'));

// Assert audio with failover
Audio::assertGenerated(fn ($p) => str_contains($p->text, 'article'));

// Assert image
Image::assertGenerated(fn ($p) => str_contains($p->prompt, 'blog'));

// Queue + Event assertions
Queue::fake();
Queue::assertPushed(GenerateBlogText::class);
Event::fake();
Event::assertDispatched(BlogStudioResultReady::class, fn ($e) => $e->type === 'text');
```

---

## 5. File Inventory (~13 new files)

| # | Type | Path | Notes |
|---|------|------|-------|
| 1 | Agent | `app/Ai/Agents/Demo/BlogTextAgent.php` | HasStructuredOutput, Gemini |
| 2 | Event | `app/Events/BlogStudioResultReady.php` | ShouldBroadcast, public channel |
| 3 | Job | `app/Jobs/Demo/GenerateBlogText.php` | ai queue, BlogTextAgent (structured) |
| 4 | Job | `app/Jobs/Demo/GenerateBlogImage.php` | ai queue, Image::of() |
| 5 | Job | `app/Jobs/Demo/GenerateBlogAudio.php` | ai queue, Audio::of() + failover |
| 6 | Livewire | `app/Livewire/Demo/BlogStudio.php` | #[Layout], Echo listener |
| 7 | View | `resources/views/livewire/demo/blog-studio.blade.php` | Blog post view + test panel |
| 8 | Test | `tests/Feature/Demo/BlogStudioTest.php` | Pest tests |
| 9 | Routes | `routes/web.php` | Add studio route (update) |
| 10 | Routes | `routes/channels.php` | Public channel registration (update) |
| 11 | Frontend | `resources/js/bootstrap.js` | Echo setup (update) |
| 12 | Nav | `resources/views/layouts/demo.blade.php` | Add sidebar entry (update) |
| 13 | Hub | `resources/views/demo/index.blade.php` | Add card (update) |

---

## 6. Implementation Details

### 6.1 Agent: `BlogTextAgent`

At `app/Ai/Agents/Demo/BlogTextAgent.php`:
- `#[Provider(Lab::Gemini)]` + `#[Model('gemini-2.5-flash')]`
- Implements `Agent`, `HasStructuredOutput`
- System prompt: professional blog writer
- Schema returns `title` (string, required), `content` (string, required), `summary` (string, required)
- No `Conversational` needed (one-shot generation)

### 6.2 Event: `BlogStudioResultReady`

```php
class BlogStudioResultReady implements ShouldBroadcast
{
    public function __construct(
        public string $sessionId,
        public string $type,        // 'text' | 'image' | 'audio'
        public array $payload,       // type-specific data
        public array $failoverLog = [],
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("demo.studio.{$this->sessionId}");
    }

    public function broadcastAs(): string
    {
        return 'studio.result';
    }
}
```

**Payload shapes:**
- `text`: `['title' => '...', 'content' => '...', 'summary' => '...']`
- `image`: `['url' => 'https://...']`
- `audio`: `['url' => 'https://...']` + `failoverLog` populated

### 6.3 Jobs (all on `ai` queue)

**GenerateBlogText** (`$sessionId`, `$topic`):
- Calls `BlogTextAgent::make()->prompt("Write a blog post about: {$topic}")`
- Broadcasts `BlogStudioResultReady` type=`text` with `['title' => ..., 'content' => ..., 'summary' => ...]`
- After broadcasting, dispatches `GenerateBlogAudio::dispatch($sessionId, $response['content'])`

**GenerateBlogImage** (`$sessionId`, `$topic`):
- `Image::of("Professional editorial blog image for: {$topic}")->landscape()->generate('gemini')`
- Stores to `public` disk at `demo/images/studio_{sessionId}.png`
- Broadcasts `BlogStudioResultReady` type=`image` with `['url' => Storage::url($path)]`

**GenerateBlogAudio** (`$sessionId`, `$articleContent`):
- Receives full article content from the text job
- Registers `ProviderFailedOver` listener to capture failover log
- `Audio::of($articleContent)->voice('nova')->generate(['gemini' => 'gemini-2.5-flash', 'openai' => 'tts-1'])`
- Stores to `public` disk at `demo/audio/studio_{sessionId}.mp3`
- Broadcasts `BlogStudioResultReady` type=`audio` with URL + failover log

### 6.4 Livewire Component: `Demo\BlogStudio`

**Properties:**
```php
public string $topic = '';
public string $sessionId = '';

// Blog post data
public string $title = '';
public string $content = '';
public string $summary = '';
public string $imageUrl = '';
public string $audioUrl = '';

// Meta
public array $failoverLog = [];
public bool $isGenerating = false;
public array $completedSteps = [];  // ['text', 'image', 'audio']
public string $error = '';
```

**Actions:**
- `mount()`: `$this->sessionId = Str::uuid()->toString()`
- `generate()`: resets state → dispatches `GenerateBlogText` + `GenerateBlogImage` in parallel → sets `$isGenerating = true`
- `#[On('echo:demo.studio.{sessionId},studio.result')]` → `onResult(array $data)`:
  - `text` → sets `$title`, `$content`, `$summary`, adds to completedSteps
  - `image` → sets `$imageUrl`, adds to completedSteps
  - `audio` → sets `$audioUrl`, `$failoverLog`, adds to completedSteps
  - When all 3 steps complete → sets `$isGenerating = false`

### 6.5 View: Blog Post Layout

The view renders a **complete blog post** as results arrive. Pending sections show skeleton loaders.

```
┌─────────────────────────────────────────────────────────────┐
│  Topic input bar                              [Generate]    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────── Blog Post Preview ────────────────────┐    │
│  │                                                     │    │
│  │  [Title]         ← large heading or skeleton        │    │
│  │  [Summary]       ← gray subtitle or skeleton        │    │
│  │                                                     │    │
│  │  🎧 Audio Player ← <audio> or skeleton bar          │    │
│  │                                                     │    │
│  │  ┌─────────────────────────────────────────────┐   │    │
│  │  │ [Featured Image — full width, landscape]    │   │    │
│  │  └─────────────────────────────────────────────┘   │    │
│  │                                                     │    │
│  │  [Article Content — rendered text]                  │    │
│  │                                                     │    │
│  │  ┌─ Failover Log ──────────────────────────────┐   │    │
│  │  │ ❌ Gemini (AudioProvider not supported)      │   │    │
│  │  │ ✅ OpenAI (tts-1/nova) — generated audio    │   │    │
│  │  └─────────────────────────────────────────────┘   │    │
│  │                                                     │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  ▸ How to Test This (collapsible <details>)                 │
│    [Pest code examples with Queue::fake, Agent::fake, etc.] │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Step indicators** at the top show progress:
- ⏳ Generating text...  |  ⏳ Generating image...  |  ⏳ Waiting for audio...
- ✅ Text ready  |  ✅ Image ready  |  ✅ Audio ready

### 6.6 "How to Test This" Panel

Collapsible `<details>` element showing **real Pest test code** (syntax-highlighted via `<pre><code>`):

```php
// Test 1: Blog generation dispatches all jobs
it('dispatches blog generation jobs', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        ->set('topic', 'AI in Healthcare')
        ->call('generate');

    Queue::assertPushed(GenerateBlogText::class);
    Queue::assertPushed(GenerateBlogImage::class);
});

// Test 2: Structured agent returns typed schema
it('generates structured blog text', function () {
    BlogTextAgent::fake();

    $response = BlogTextAgent::make()->prompt('Write about AI');

    expect($response['title'])->toBeString();
    expect($response['content'])->toBeString();
    expect($response['summary'])->toBeString();
});

// Test 3: Audio failover from Gemini to OpenAI
it('handles audio failover from Gemini to OpenAI', function () {
    Audio::fake();
    Event::fake();

    $job = new GenerateBlogAudio('session-123', 'Full article text here...');
    $job->handle();

    Audio::assertGenerated(fn ($p) => str_contains($p->text, 'Full article'));
    Event::assertDispatched(BlogStudioResultReady::class, fn ($e) => $e->type === 'audio');
});

// Test 4: Image generation
it('generates a featured image', function () {
    Image::fake();
    Event::fake();

    $job = new GenerateBlogImage('session-123', 'AI in Healthcare');
    $job->handle();

    Image::assertGenerated(fn ($p) => str_contains($p->prompt, 'Healthcare'));
});

// Test 5: Broadcast event
it('broadcasts results via Reverb', function () {
    Event::fake();

    BlogStudioResultReady::dispatch('session-123', 'text', [
        'title' => 'Test Title',
        'content' => 'Test content',
        'summary' => 'Test summary',
    ]);

    Event::assertDispatched(BlogStudioResultReady::class, fn ($e) =>
        $e->type === 'text' && $e->payload['title'] === 'Test Title'
    );
});
```

### 6.7 Test File: `tests/Feature/Demo/BlogStudioTest.php`

Actual runnable Pest tests:

1. **Page renders** — `GET /demo/studio` returns 200
2. **Generate dispatches jobs** — `Queue::fake()` + `Livewire::test()` asserts `GenerateBlogText` + `GenerateBlogImage` pushed
3. **Receiving text broadcast updates state** — call `onResult()` directly, assert `$title`, `$content`, `$summary` set
4. **Receiving image broadcast updates state** — call `onResult()`, assert `$imageUrl` set
5. **Receiving audio broadcast updates state** — call `onResult()`, assert `$audioUrl` + `$failoverLog` set
6. **All steps complete clears generating state** — after 3 `onResult()` calls, assert `$isGenerating === false`

---

## 7. Build Order

| # | Step | Time |
|---|------|------|
| 1 | Install Echo + configure `bootstrap.js` + verify `.env` | 10 min |
| 2 | Create `BlogTextAgent` (structured agent) | 10 min |
| 3 | Create `BlogStudioResultReady` event | 10 min |
| 4 | Create 3 queued jobs (text → audio chained, image parallel) | 30 min |
| 5 | Register public channel in `channels.php` | 5 min |
| 6 | Create `BlogStudio` Livewire component | 20 min |
| 7 | Create the Blade view (blog post layout + test panel) | 30 min |
| 8 | Add route + nav entries | 10 min |
| 9 | Write Pest tests | 30 min |
| 10 | Run `composer check`, fix issues | 15 min |

---

## 8. Decisions Made

1. **✅ Echo installation** — Install `laravel-echo` + `pusher-js` as part of this task. One-time setup that also unblocks existing Dost pipeline.

2. **✅ Full article narration** — Audio narrates the full `content` from the structured response. No truncation.

3. **✅ Structured output** — Blog text uses `BlogTextAgent` with `HasStructuredOutput` returning `{title, content, summary}` instead of separate title/article jobs. This is cleaner (one agent call, typed schema) and demos `HasStructuredOutput` feature.

4. **3 jobs instead of 4** — Since title + content come from one structured agent call, we now have 3 jobs: `GenerateBlogText`, `GenerateBlogImage`, `GenerateBlogAudio`. Text and image run in parallel; audio chains after text.

5. **Public channel** — `demo.studio.{uuid}` is a public channel (no auth). Each browser tab gets its own UUID, preventing cross-tab interference.

6. **Blog post view** — Final layout reads like a published blog post: title → summary → audio player → featured image → article content → failover log → test panel.

