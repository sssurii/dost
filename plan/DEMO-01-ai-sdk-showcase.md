# DEMO-01: Laravel AI SDK Showcase (7 Demo Pages)

**Purpose:** Live demo / presentation showcasing `laravel/ai` SDK capabilities
**Phase:** Standalone (not part of Dost product roadmap)
**Estimate:** ~4–6h total
**Status:** ✅ Implementation complete (March 26, 2026)
**Date:** March 26, 2026

---

## 1. Objective

Build 7 self-contained Livewire pages — one per PPT slide — each framed as a **real-world product feature** that naturally showcases one `laravel/ai` capability.
All pages live under `/demo/*` (public, no auth) and are fully independent from Dost's voice pipeline.

---

## 2. PPT Slide → Demo Page Mapping

| Slide | PPT Title | Route | Real-World Use Case | SDK Feature Shown |
|-------|-----------|-------|--------------------|--------------------|
| 2 | Core Integration | `/demo/writer` | **AI Content Writer** — write marketing copy, compare tone/quality across AI providers | Provider switching via unified API |
| 3 | Multimodal Generation | `/demo/blog` | **Blog Post Generator** — enter a topic → get article + auto-generated featured image | Text + Image generation |
| 4 | Audio Intelligence | `/demo/podcast` | **Podcast Toolkit** — convert article to audio episode (TTS) + transcribe uploaded audio (STT) | Audio::of() + Transcription::of() |
| 5 | Knowledge & Search | `/demo/helpdesk` | **Smart Help Desk** — semantic search across support/knowledge base articles | Embeddings + vector similarity |
| 6 | Autonomous Logic | `/demo/analyst` | **Content Analyst Agent** — chat agent that analyzes pasted text using tools (word stats, readability, keywords) | Agent + HasTools |
| 7 | Enterprise Resiliency | `/demo/alerts` | **Mission-Critical Alert Writer** — AI drafts emergency notifications with automatic provider failover | Failover + ProviderFailedOver event |

Hub page at `/demo` links to all 7 with use-case descriptions.

---

## 3. API Keys Required

| Key | Used By | Required? |
|-----|---------|-----------|
| `GEMINI_API_KEY` | Slides 2, 3, 5, 6, 7 (text, image, embeddings, agent, failover) | **Yes** |
| `OPENAI_API_KEY` | Slide 4 (TTS + STT), Slide 2 (provider comparison) | **Yes** |
| `ANTHROPIC_API_KEY` | Slide 2 (optional third provider comparison) | Optional |

### Provider Capability Matrix (confirmed from vendor source)

| Capability | SDK Class | Gemini | OpenAI | Notes |
|---|---|---|---|---|
| Text generation | `Agent->prompt()` | ✅ | ✅ | Via `Promptable` trait |
| Streaming | `Agent->stream()` | ✅ | ✅ | Via `Promptable` trait |
| Image generation | `Image::of()` | ✅ | ✅ | Gemini is `default_for_images` in config |
| Embeddings | `Embeddings::for()` | ✅ | ✅ | Either provider works |
| TTS (audio) | `Audio::of()` | ❌ | ✅ | `AudioProvider` only on OpenAI + ElevenLabs |
| STT (transcribe) | `Transcription::of()` | ❌ | ✅ | `TranscriptionProvider` only on OpenAI + ElevenLabs |
| Failover | Array provider param | ✅ | ✅ | SDK fires `ProviderFailedOver` event |
| Tools | `HasTools` interface | ✅ | ✅ | Agent calls PHP tool classes |

---

## 4. SDK API Reference (quick cheat sheet)

### Text — via Agents (no standalone `Ai::text()`)
```php
use Laravel\Ai\AnonymousAgent;

// Quick one-off prompt
$agent = new AnonymousAgent('You are a helpful assistant.', messages: [], tools: []);
$response = $agent->prompt('Explain Laravel in 2 sentences.', provider: 'gemini');
$response->text; // string

// Streaming
$stream = $agent->stream('Explain Laravel.', provider: 'gemini');
foreach ($stream as $chunk) { echo $chunk; }
```

### Image
```php
use Laravel\Ai\Image;

$response = Image::of('A sunset over the Ganges river, watercolor style')
    ->square()           // '1:1' | ->portrait() '2:3' | ->landscape() '3:2'
    ->quality('medium')  // 'low' | 'medium' | 'high'
    ->generate('gemini');
$response->url;          // URL of generated image
$response->base64;       // or base64 data
```

### Audio (TTS) — OpenAI / ElevenLabs only
```php
use Laravel\Ai\Audio;

$response = Audio::of('Hello, welcome to our demo!')
    ->female()            // ->male() | ->voice('nova')
    ->instructions('Speak with a warm, friendly tone')
    ->generate('openai');
// $response contains audio content — save to file
```

### Transcription (STT) — OpenAI / ElevenLabs only
```php
use Laravel\Ai\Transcription;

// From uploaded file (Livewire WithFileUploads)
$response = Transcription::of($uploadedFile)
    ->language('en')
    ->generate('openai');
$response->text; // transcribed string

// From storage disk
$response = Transcription::fromStorage('recordings/audio.m4a', 'public')
    ->generate('openai');
```

### Embeddings
```php
use Laravel\Ai\Embeddings;

$response = Embeddings::for(['Laravel is a PHP framework', 'Redis is a cache store'])
    ->dimensions(1536)
    ->generate('gemini');
$response->embeddings; // array of float arrays: [[0.012, -0.034, ...], [...]]
```

### Agents with Tools
```php
// Agent class implements Agent, HasTools
#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
final class ToolDemoAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string { return '...'; }
    public function messages(): iterable { return []; }
    public function tools(): iterable { return [new TextAnalyzerTool]; }
}

// Tool class implements Tool
final class TextAnalyzerTool implements Tool
{
    public function description(): string { return 'Analyzes text...'; }
    public function handle(Request $request): string { return json_encode([...]); }
    public function schema(JsonSchema $schema): array {
        return ['text' => $schema->string()->required()];
    }
}
```

### Failover (pass array of providers)
```php
// SDK iterates providers in order; fires ProviderFailedOver on each failure
$response = $agent->prompt('Hello', provider: [
    'demo-broken' => 'gpt-4o',     // will fail (invalid key)
    'gemini' => 'gemini-2.5-flash', // fallback succeeds
]);
```

---

## 5. Architecture

### Shared Layout
`resources/views/layouts/demo.blade.php` — clean desktop-friendly shell:
- Left sidebar: slide number + title list (highlight active)
- Right content area: renders Livewire component via `{{ $slot }}`
- No mobile bottom nav, no auth checks
- Tailwind: `bg-neutral-950 text-white` base, cards in `bg-neutral-900`

### Routes (all public, no auth)
```php
// routes/web.php — add inside existing file
Route::prefix('demo')->group(function () {
    Route::view('/', 'demo.index')->name('demo.index');
    Route::get('/writer', Demo\ContentWriter::class)->name('demo.writer');
    Route::get('/blog', Demo\BlogGenerator::class)->name('demo.blog');
    Route::get('/podcast', Demo\PodcastToolkit::class)->name('demo.podcast');
    Route::get('/helpdesk', Demo\SmartHelpDesk::class)->name('demo.helpdesk');
    Route::get('/analyst', Demo\ContentAnalyst::class)->name('demo.analyst');
    Route::get('/alerts', Demo\AlertWriter::class)->name('demo.alerts');
});
```

---

## 6. Per-Slide Implementation Details

### Slide 2 — AI Content Writer (`Demo\ContentWriter`)

**Real-world scenario:** A marketing team uses AI to draft copy. They want to compare outputs from different providers to pick the best tone for their brand.

**What the audience sees:** User types "Write a product launch email for a new fitness tracker" → picks a provider → gets polished copy with a latency badge. Switches provider → same prompt, different style. "Look — one API, any provider."

**Livewire component:**
- Properties: `$prompt` (textarea, placeholder: "Describe the content you need..."), `$selectedProvider` (radio: gemini / openai / anthropic), `$response`, `$providerUsed`, `$latencyMs`
- Action `generate()`: creates `AnonymousAgent('You are an expert marketing copywriter. Write compelling, professional content based on the user\'s request.', [], [])`, calls `->prompt($this->prompt, provider: $this->selectedProvider)`, measures `microtime()`
- View: split layout — left: prompt + provider picker + "Generate" button; right: response card with provider badge (colored chip) + latency + word count
- Disable providers whose API key is empty (`config("ai.providers.{$name}.key")`)

**Files:**
- `app/Livewire/Demo/ContentWriter.php`
- `resources/views/livewire/demo/content-writer.blade.php`

---

### Slide 3 — Blog Post Generator (`Demo\BlogGenerator`)

**Real-world scenario:** A blogger enters a topic and gets a full article draft plus an auto-generated featured image — ready to publish.

**What the audience sees:** User types "The future of AI in healthcare" → clicks Generate → article appears on the left, a matching featured image renders on the right. Both from one prompt, one SDK.

**Livewire component:**
- Properties: `$topic` (input), `$article` (string, markdown-ish), `$imageUrl` (string), `$isLoading` (bool)
- Action `generate()`:
  1. Text: `AnonymousAgent('You are a professional blog writer. Write a 3-paragraph blog post with a catchy title on the given topic. Use markdown formatting.', [], [])->prompt("Write a blog post about: {$this->topic}", provider: 'gemini')`
  2. Image: `Image::of("Professional blog featured image for an article about: {$this->topic}. Modern, clean, editorial style.")->landscape()->generate('gemini')`
- View: topic input at top → two-column card below (left: rendered article, right: featured image) → loading spinner overlay via `wire:loading`
- Image displayed via base64 data URI or URL depending on response

**Files:**
- `app/Livewire/Demo/BlogGenerator.php`
- `resources/views/livewire/demo/blog-generator.blade.php`

---

### Slide 4 — Podcast Toolkit (`Demo\PodcastToolkit`)

**Real-world scenario:** A content team repurposes written articles into podcast episodes (TTS), and transcribes interview recordings for show notes (STT).

**What the audience sees:** Two cards. **Card 1:** paste an article → click "Generate Episode" → an `<audio>` player appears with a natural voice reading it. **Card 2:** upload an audio file → click "Transcribe" → full text transcript appears.

**Requires:** `OPENAI_API_KEY`

**Livewire component:**
- Uses `WithFileUploads` trait
- **TTS card:** `$articleText` (textarea) → `Audio::of($text)->voice('nova')->instructions('Read this as a professional podcast host, with clear enunciation and natural pacing')->generate('openai')` → save to `storage/app/public/demo/episode_{timestamp}.mp3` → `$episodeUrl` for `<audio controls>` player
- **STT card:** `$audioFile` (file upload, accept `.mp3,.m4a,.wav,.webm`) → `Transcription::of($audioFile)->language('en')->generate('openai')` → `$transcript` displayed in a styled text block
- View: two-card side-by-side layout (TTS left, STT right), each with its own loading state

**Files:**
- `app/Livewire/Demo/PodcastToolkit.php`
- `resources/views/livewire/demo/podcast-toolkit.blade.php`

---

### Slide 5 — Smart Help Desk (`Demo\SmartHelpDesk`)

**Real-world scenario:** A SaaS company has a knowledge base. Instead of keyword search, customers type natural questions and get the most relevant articles ranked by meaning.

**What the audience sees:** Pre-loaded knowledge base (8 articles visible). User types "How do I handle failed background tasks?" → results re-rank by semantic relevance with similarity % bars. The top result is "Laravel Queues" (not a keyword match for "background tasks" — it's a meaning match).

**Database:** PostgreSQL `demo_documents` table:
```sql
id            BIGSERIAL PRIMARY KEY
title         VARCHAR(255)
content       TEXT
embedding     JSONB          -- float array stored as JSON
created_at    TIMESTAMP
updated_at    TIMESTAMP
```

**Seed data (8 support/knowledge base articles):**
1. "Getting Started with Routing" — how to define web routes, route parameters, named routes, and route groups in your application
2. "Database Models & Relationships" — using Eloquent ORM for models, belongsTo, hasMany, many-to-many, and query scopes
3. "Building Dynamic Views" — Blade template engine, template inheritance, components, slots, and conditional directives
4. "Background Job Processing" — dispatching queued jobs, configuring workers, handling failed jobs, retry strategies, and job batching
5. "User Authentication & Security" — setting up login, registration, password reset, API tokens, and role-based access control
6. "Automated Testing Guide" — writing feature and unit tests with Pest, mocking services, database factories, and CI integration
7. "Event-Driven Architecture" — event/listener pattern, broadcasting real-time events via WebSockets, and event subscribers
8. "HTTP Middleware Pipeline" — creating middleware, applying to routes and groups, rate limiting, and CORS configuration

**Artisan command:** `demo:seed-embeddings`
- Truncates `demo_documents`
- Inserts 8 docs with realistic multi-sentence content
- Calls `Embeddings::for($allContents)->generate('gemini')` (single batch API call)
- Updates each row's `embedding` column with the vector

**Livewire component:**
- Properties: `$query`, `$results` (array of `[title, content, score]`), `$allDocuments` (for initial display)
- Action `search()`: embed query via `Embeddings::for([$query])->generate('gemini')` → cosine similarity against all `demo_documents.embedding` in PHP → sort descending → top 5
- View: search bar at top → knowledge base articles listed below with similarity % progress bar per result, color-coded (green ≥70%, amber ≥40%, red <40%)

**Cosine similarity** — pure PHP helper in `app/Support/CosineSimilarity.php`:
```php
final class CosineSimilarity
{
    /** @param float[] $a  @param float[] $b */
    public static function calculate(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;
        for ($i = 0, $n = count($a); $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }
        $denominator = sqrt($normA) * sqrt($normB);
        return $denominator > 0 ? $dot / $denominator : 0.0;
    }
}
```

**Files:**
- Migration: `create_demo_documents_table`
- `app/Models/DemoDocument.php` + factory
- `app/Console/Commands/SeedDemoEmbeddings.php`
- `app/Support/CosineSimilarity.php`
- `app/Livewire/Demo/SmartHelpDesk.php`
- `resources/views/livewire/demo/smart-help-desk.blade.php`

---

### Slide 6 — Content Analyst Agent (`Demo\ContentAnalyst`)

**Real-world scenario:** An editorial team pastes draft content and chats with an AI analyst. The agent autonomously uses a text analysis tool to provide word count, reading time, readability score, and keyword extraction — then explains findings conversationally.

**What the audience sees:** Chat interface. User pastes a paragraph → agent responds: "Let me analyze that for you..." → calls TextAnalyzer tool → reports back: "Your text is 142 words, ~34 seconds reading time, with a Flesch-Kincaid grade level of 8. Top keywords: Laravel, AI, middleware. This reads well for a tech blog audience!"

**Agent:** `app/Ai/Agents/Demo/ContentAnalystAgent.php`
- `#[Provider(Lab::Gemini)]` + `#[Model('gemini-2.5-flash')]`
- Implements `Agent`, `HasTools`
- System prompt: "You are a professional content analyst assistant. When the user gives you text to analyze, ALWAYS use your TextAnalyzer tool first to get accurate statistics. Then explain the results in a friendly, helpful way. Mention specific numbers from the tool output. If the user asks a general question, answer normally without using the tool."
- `tools()` returns `[new TextAnalyzerTool]`

**Tool:** `app/Ai/Tools/TextAnalyzerTool.php`
- Pure PHP — no external API, instant execution
- Schema: `text` (string, required)
- `handle()` returns JSON:
```json
{
    "word_count": 142,
    "sentence_count": 8,
    "paragraph_count": 2,
    "avg_words_per_sentence": 17.75,
    "reading_time_seconds": 34,
    "top_keywords": ["laravel", "ai", "middleware"],
    "flesch_reading_ease": 65.2,
    "grade_level": "8th grade"
}
```

**Livewire component:**
- Properties: `$messages` (array of `['role' => 'user'|'assistant', 'content' => '...']`), `$userInput` (string)
- Action `send()`:
  1. Append `['role' => 'user', 'content' => $this->userInput]` to messages
  2. Call `ContentAnalystAgent::make()->prompt($this->userInput)`
  3. Append `['role' => 'assistant', 'content' => $response->text]`
  4. Clear `$userInput`
- View: scrollable chat area (user messages right-aligned in amber bubble, assistant left-aligned in neutral bubble) + input bar at bottom with send button
- Suggested starter prompts as clickable chips: "Analyze this text:", "What's the reading level of..."

**Files:**
- `app/Ai/Agents/Demo/ContentAnalystAgent.php`
- `app/Ai/Tools/TextAnalyzerTool.php`
- `app/Livewire/Demo/ContentAnalyst.php`
- `resources/views/livewire/demo/content-analyst.blade.php`

---

### Slide 7 — Mission-Critical Alert Writer (`Demo\AlertWriter`)

**Real-world scenario:** An emergency notification system drafts urgent alerts (natural disaster, service outage, security breach). AI must never fail — if the primary provider is down, it automatically fails over to a backup.

**What the audience sees:** User picks a scenario ("Service Outage" / "Security Alert" / "Weather Warning") → clicks "Draft Alert" → a visual timeline appears: ❌ Primary (OpenAI) — connection failed → ✅ Fallback (Gemini) — generated successfully → the drafted alert message appears. The key message: "Your AI features stay up even when a provider goes down."

**Config change:** Add to `config/ai.php` providers array:
```php
'demo-broken' => [
    'driver' => 'openai',
    'key' => 'sk-INVALID-KEY-FOR-DEMO',
],
```

**Livewire component:**
- Properties: `$scenario` (select: service_outage / security_alert / weather_warning), `$response`, `$failoverLog` (array of step objects), `$isLoading`
- Scenario prompts (mapped):
  - `service_outage`: "Draft an urgent notification to customers about a 2-hour service outage affecting payment processing. Be clear, empathetic, and include next steps."
  - `security_alert`: "Draft a security advisory to users about a detected unauthorized access attempt. Be direct, include immediate actions users should take."
  - `weather_warning`: "Draft an emergency weather alert for a severe thunderstorm warning in the Mumbai metropolitan area. Include safety instructions."
- Action `generate()`:
  1. Reset `$failoverLog`, set `$isLoading = true`
  2. Register listener: `Event::listen(ProviderFailedOver::class, ...)` — pushes `['provider' => ..., 'status' => 'failed', 'error' => ...]` to log
  3. Call `AnonymousAgent('You are an emergency communications specialist. Write clear, professional, urgent notifications.', [], [])->prompt($scenarioPrompt, provider: ['demo-broken' => 'gpt-4o', 'gemini' => 'gemini-2.5-flash'])`
  4. Append `['provider' => 'Gemini', 'status' => 'success']` to log
  5. Set `$response = $result->text`
- View: scenario picker (3 cards or dropdown) + "Draft Alert" button → failover timeline (vertical step indicator: red ❌ / green ✅ per provider with timing) → drafted alert displayed in an "alert preview" card styled like an actual notification

**Files:**
- `config/ai.php` — add `demo-broken` provider entry
- `app/Livewire/Demo/AlertWriter.php`
- `resources/views/livewire/demo/alert-writer.blade.php`

---

## 7. File Inventory (~22 files)

| # | Type | Path | New/Update |
|---|------|------|-----------|
| 1 | Layout | `resources/views/layouts/demo.blade.php` | New |
| 2 | Hub view | `resources/views/demo/index.blade.php` | New |
| 3 | Routes | `routes/web.php` | Update |
| 4 | Livewire | `app/Livewire/Demo/ContentWriter.php` | New |
| 5 | View | `resources/views/livewire/demo/content-writer.blade.php` | New |
| 6 | Livewire | `app/Livewire/Demo/BlogGenerator.php` | New |
| 7 | View | `resources/views/livewire/demo/blog-generator.blade.php` | New |
| 8 | Livewire | `app/Livewire/Demo/PodcastToolkit.php` | New |
| 9 | View | `resources/views/livewire/demo/podcast-toolkit.blade.php` | New |
| 10 | Migration | `database/migrations/xxxx_create_demo_documents_table.php` | New |
| 11 | Model | `app/Models/DemoDocument.php` | New |
| 12 | Factory | `database/factories/DemoDocumentFactory.php` | New |
| 13 | Command | `app/Console/Commands/SeedDemoEmbeddings.php` | New |
| 14 | Helper | `app/Support/CosineSimilarity.php` | New |
| 15 | Livewire | `app/Livewire/Demo/SmartHelpDesk.php` | New |
| 16 | View | `resources/views/livewire/demo/smart-help-desk.blade.php` | New |
| 17 | Agent | `app/Ai/Agents/Demo/ContentAnalystAgent.php` | New |
| 18 | Tool | `app/Ai/Tools/TextAnalyzerTool.php` | New |
| 19 | Livewire | `app/Livewire/Demo/ContentAnalyst.php` | New |
| 20 | View | `resources/views/livewire/demo/content-analyst.blade.php` | New |
| 21 | Livewire | `app/Livewire/Demo/AlertWriter.php` | New |
| 22 | View | `resources/views/livewire/demo/alert-writer.blade.php` | New |
| 23 | Config | `config/ai.php` | Update |

---

## 8. Build Order (fastest path)

| Order | Slide | Why this order |
|-------|-------|----------------|
| 0 | Layout + Hub + Routes | Foundation — all demos need this |
| 1 | Slide 2 — Content Writer | Simplest (text only, no extra classes) |
| 2 | Slide 7 — Alert Writer (Failover) | Text + config tweak, dramatic demo moment |
| 3 | Slide 3 — Blog Generator | Text + image, two API calls |
| 4 | Slide 6 — Content Analyst Agent | Agent class + tool class + chat UI |
| 5 | Slide 5 — Smart Help Desk | Migration + model + seeder + embeddings |
| 6 | Slide 4 — Podcast Toolkit | TTS + STT + file upload (most parts) |

---

## 9. UI Design Notes

- **Layout:** Clean desktop presentation style (not mobile-first like Dost app)
- **Palette:** `bg-neutral-950` base, `bg-neutral-900` cards, `text-white`, accents `text-amber-400`
- **Each demo:** title bar showing "Slide N: Title" + brief subtitle explaining the SDK feature
- **Loading:** `wire:loading` spinners on all generate buttons
- **Errors:** `try/catch` in every Livewire action → red alert box

---

## 10. Pre-Demo Checklist

- [ ] `GEMINI_API_KEY` set in `.env`
- [ ] `OPENAI_API_KEY` set in `.env`
- [ ] Run migration: `./bin/artisan migrate`
- [ ] Seed embeddings: `./bin/artisan demo:seed-embeddings`
- [ ] Build frontend: `./bin/npm run build`
- [ ] Verify all 7 pages load: visit `/demo`
- [ ] Test each demo with a sample prompt
- [ ] Have backup prompts ready for live demo

---

## 11. Cleanup (post-presentation)

All demo code is isolated in `Demo/` subdirectories + `demo_*` DB table.

Rollback: `./bin/artisan migrate:rollback --step=1`, then delete the files listed in Section 7.

---

## 12. Reference

- **Laravel AI SDK docs:** https://laravel.com/docs/13.x/ai-sdk
- **Existing TutorAgent:** `app/Ai/Agents/TutorAgent.php` — reference for agent structure
- **Tool stub:** `stubs/tool.stub` — reference for tool structure
- **Config:** `config/ai.php` — provider configuration
- **Database:** PostgreSQL, connection `pgsql` in `config/database.php`

