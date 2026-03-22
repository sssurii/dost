# VOICE-02: TutorAgent AI Integration
**Phase:** 3 — Voice Core
**Complexity:** 4 | **Estimate:** 4h
**Depends on:** VOICE-01 (Recording model + event), RND-01 (audio format confirmed), INF-03 (`laravel/ai` SDK installed)
**Blocks:** VOICE-03 (needs AI text response to generate TTS audio)
**Status:** ✅ Completed March 22, 2026
> **Last updated:** 2026-03-22 — implementation complete; two plan deviations noted below.
---
## 1. Objective
Build the **"Soul"** of Dost. Wire the voice pipeline so that:
1. A `RecordingFinished` event triggers the `ProcessRecording` listener (already stubbed)
2. The listener calls `TutorProcessor`, which sends the audio to Gemini 2.5 Flash via `laravel/ai`
3. Gemini returns a typed `{transcript, response}` — no regex or JSON parsing needed
4. The recording is saved as `completed` with both fields
5. `AiResponseReady` is broadcast via Reverb → Livewire
6. `GenerateTtsAudio` job is dispatched (stub for VOICE-03)
---
## 2. SDK Facts (confirmed against official docs + source)
| Fact | Detail |
|------|--------|
| Agent namespace | `App\Ai\Agents` (lowercase `i`) |
| Scaffold command | `php artisan make:agent TutorAgent --structured --no-interaction` |
| Provider/model | PHP 8 attributes: `#[Provider(Lab::Gemini)]` + `#[Model('gemini-2.5-flash')]` |
| Audio attachment | `Files\Audio::fromStorage($path, $disk)` — relative disk path, not absolute |
| Structured response | Returns `StructuredAgentResponse` (implements `ArrayAccess`) → `$response['transcript']` |
| Conversation start | `(new TutorAgent)->forUser($user)->prompt(...)` |
| Conversation continue | `(new TutorAgent)->continue($conversationId, as: $user)->prompt(...)` — `as:` is named |
| Conversation ID on response | `$response->conversationId` |
| DB tables (SDK-owned) | `agent_conversations` — columns: `id, user_id, title, timestamps` (no `agent` column) |
| Testing | `TutorAgent::fake()` auto-generates structured fake data matching the schema |
| Test assertions | `TutorAgent::assertPrompted(fn($p) => $p->contains('...'))` |
| Prevent stray prompts | `TutorAgent::fake()->preventStrayPrompts()` |
---
## 3. Architecture
```
RecordingFinished Event
        │
        ▼
ProcessRecording Listener   ← already exists (stub only)
  queue = 'ai', tries = 2, timeout = 30
        │
        ▼
TutorProcessor::process(Recording $recording)
  ├── $recording->markAsProcessing()
  ├── resolveAgent($user)   ← today's conversation or new one
  ├── $agent->prompt('Transcribe and respond as Dost.',
  │       attachments: [Audio::fromStorage($recording->path, 'public')])
  ├── $recording->markAsCompleted($response['transcript'], $response['response'])
  └── return TutorResult
        │
        ▼
AiResponseReady::dispatch($recording)   ← broadcast on private channel
        │
        ▼
GenerateTtsAudio::dispatch($recording)  ← stub job, implemented in VOICE-03
```
---
## 4. Files to Create / Modify
| Action | File | Notes |
|--------|------|-------|
| **Create** | `app/Ai/Agents/TutorAgent.php` | `make:agent --structured` then implement |
| **Create** | `app/Services/Ai/TutorResult.php` | `readonly` value object |
| **Create** | `app/Services/Ai/TutorProcessor.php` | `make:class` then implement |
| **Create** | `app/Events/AiResponseReady.php` | `make:event` then implement |
| **Create** | `app/Jobs/GenerateTtsAudio.php` | `make:job` stub only (VOICE-03) |
| **Modify** | `app/Listeners/ProcessRecording.php` | Full implementation (stub exists) |
| **Modify** | `bootstrap/app.php` | Register `RecordingFinished → ProcessRecording` |
| **Create** | `tests/Feature/Voice/TutorProcessorTest.php` | Pest tests |
---
## 5. Implementation
### Step 1 — Scaffold
```bash
docker compose exec app php artisan make:agent TutorAgent --structured --no-interaction
docker compose exec app php artisan make:event AiResponseReady --no-interaction
docker compose exec app php artisan make:job GenerateTtsAudio --no-interaction
```
> `make:agent --structured` places the file at `app/Ai/Agents/TutorAgent.php`.
---
### Step 2 — `TutorAgent`
```php
<?php
declare(strict_types=1);
namespace App\Ai\Agents;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
final class TutorAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;
    private const SYSTEM_PROMPT = <<<'PROMPT'
    You are Dost — a warm, encouraging English speaking partner for Indian learners.
    YOUR PERSONALITY:
    - You are like a supportive dost (friend) who happens to be great at English
    - You use Indian English naturally: "yaar", "achha", "wah!", "bilkul", "ek second"
    - You are enthusiastic and genuinely celebrate every attempt to speak
    - You keep things light, fun, and conversational
    YOUR RULES (follow strictly):
    1. NEVER correct grammar, pronunciation, or word choice — EVER
    2. NEVER say anything discouraging, even indirectly
    3. NEVER say "That's wrong", "You made a mistake", "Actually...", or imply any error
    4. ALWAYS respond to the MEANING and INTENT of what the user said, not the words
    5. ALWAYS end with a simple, encouraging follow-up question
    6. Keep responses SHORT — 2 to 3 sentences maximum (this is voice playback)
    7. Be SPECIFIC in your encouragement — react to what they actually said
    EXAMPLE GOOD RESPONSES:
    - "Wah! That's a great point, yaar! Tell me more — what happened next?"
    - "Achha, I love how you explained that! So, what do you think about it?"
    - "Bilkul right! You explained that so clearly! Have you talked about this with anyone else?"
    EXAMPLE BAD RESPONSES (never do this):
    - "Good try! But the correct way to say it is..." ❌
    - "Almost! Next time try to use..." ❌
    - "That's an interesting attempt." ❌ (sounds condescending)
    PROMPT;
    public function instructions(): string
    {
        return self::SYSTEM_PROMPT;
    }
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transcript' => $schema->string()
                ->description('Verbatim transcription of what the user said in the audio.')
                ->required(),
            'response' => $schema->string()
                ->description('Warm, encouraging 2–3 sentence Dost reply ending with a follow-up question.')
                ->required(),
        ];
    }
}
```
---
### Step 3 — `TutorResult` Value Object
```php
<?php
declare(strict_types=1);
namespace App\Services\Ai;
use App\Models\Recording;
final readonly class TutorResult
{
    public function __construct(
        public string $transcript,
        public string $response,
        public Recording $recording,
    ) {}
}
```
---
### Step 4 — `TutorProcessor` Service
```php
<?php
declare(strict_types=1);
namespace App\Services\Ai;
use App\Ai\Agents\TutorAgent;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Responses\StructuredAgentResponse;
final class TutorProcessor
{
    /**
     * Process a recording: send audio to Gemini, receive structured response, save to DB.
     *
     * @throws \Throwable
     */
    public function process(Recording $recording): TutorResult
    {
        $recording->markAsProcessing();
        try {
            $agent = $this->resolveAgent($recording->user);
            /** @var StructuredAgentResponse $response */
            $response = $agent->prompt(
                'Transcribe the audio and respond as Dost.',
                attachments: [Audio::fromStorage($recording->path, 'public')],
            );
            $transcript = (string) $response['transcript'];
            $aiResponse = (string) $response['response'];
            $recording->markAsCompleted($transcript, $aiResponse);
            Log::info('TutorProcessor: completed', [
                'recording_id'    => $recording->id,
                'conversation_id' => $response->conversationId,
            ]);
            return new TutorResult(
                transcript: $transcript,
                response:   $aiResponse,
                recording:  $recording->fresh() ?? $recording,
            );
        } catch (\Throwable $e) {
            $recording->markAsFailed();
            Log::error('TutorProcessor: failed', [
                'recording_id' => $recording->id,
                'error'        => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    /**
     * Resolve today's conversation or start a fresh one.
     * New conversation each day; context preserved within the same day.
     */
    private function resolveAgent(User $user): TutorAgent
    {
        $todayConversation = DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->latest('updated_at')
            ->first();
        if ($todayConversation !== null) {
            return (new TutorAgent)->continue($todayConversation->id, as: $user);
        }
        return (new TutorAgent)->forUser($user);
    }
}
```
> **Why `DB::table()`?** The SDK does not expose an Eloquent model for `agent_conversations`. This is the only option and is acceptable per project rules ("no raw queries unless truly necessary" — this qualifies).
---
### Step 5 — `AiResponseReady` Broadcast Event
```php
<?php
declare(strict_types=1);
namespace App\Events;
use App\Models\Recording;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
final class AiResponseReady implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    public function __construct(
        public readonly Recording $recording,
    ) {}
    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->recording->user_id),
        ];
    }
    public function broadcastAs(): string
    {
        return 'recording.completed';
    }
    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'recording_id'  => $this->recording->id,
            'transcript'    => $this->recording->transcript,
            'response_text' => $this->recording->ai_response_text,
            'audio_url'     => null, // populated by VOICE-03
        ];
    }
}
```
---
### Step 6 — `GenerateTtsAudio` Job (stub)
```php
<?php
declare(strict_types=1);
namespace App\Jobs;
use App\Models\Recording;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
final class GenerateTtsAudio implements ShouldQueue
{
    use Queueable;
    public string $queue = 'tts';
    public function __construct(
        public readonly Recording $recording,
    ) {}
    /**
     * Full implementation in VOICE-03.
     */
    public function handle(): void
    {
        // VOICE-03: generate TTS audio and update $this->recording->ai_response_audio_path
    }
}
```
---
### Step 7 — `ProcessRecording` Listener (full implementation)
```php
<?php
declare(strict_types=1);
namespace App\Listeners;
use App\Events\AiResponseReady;
use App\Events\RecordingFinished;
use App\Jobs\GenerateTtsAudio;
use App\Services\Ai\TutorProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
final class ProcessRecording implements ShouldQueue
{
    use InteractsWithQueue;
    public string $queue   = 'ai';
    public int    $tries   = 2;
    public int    $timeout = 30;
    public int    $backoff = 5;
    public function __construct(
        private readonly TutorProcessor $processor,
    ) {}
    public function handle(RecordingFinished $event): void
    {
        $recording = $event->recording;
        if (! $recording->isPending()) {
            return; // idempotency guard
        }
        $result = $this->processor->process($recording);
        AiResponseReady::dispatch($result->recording);
        GenerateTtsAudio::dispatch($result->recording);
    }
    public function failed(RecordingFinished $event, \Throwable $exception): void
    {
        $event->recording->markAsFailed();
        Log::error('ProcessRecording permanently failed', [
            'recording_id' => $event->recording->id,
            'error'        => $exception->getMessage(),
        ]);
    }
}
```
---
### Step 8 — Register Listener in `bootstrap/app.php`
Laravel 13 uses `->withEvents()` on the Application builder — no separate `EventServiceProvider` needed:
```php
->withEvents(
    listen: [
        \App\Events\RecordingFinished::class => [
            \App\Listeners\ProcessRecording::class,
        ],
    ]
)
```
---
## 6. Pest Tests
File: `tests/Feature/Voice/TutorProcessorTest.php`
```php
<?php
declare(strict_types=1);
use App\Ai\Agents\TutorAgent;
use App\Events\AiResponseReady;
use App\Events\RecordingFinished;
use App\Jobs\GenerateTtsAudio;
use App\Listeners\ProcessRecording;
use App\Models\Recording;
use App\Models\User;
use App\Services\Ai\TutorProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
describe('TutorProcessor', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->user = User::factory()->create();
    });
    it('marks recording completed with transcript and response', function () {
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');
        TutorAgent::fake([
            ['transcript' => 'Hello my name is Raj.', 'response' => 'Wah, great to meet you Raj yaar!'],
        ]);
        $recording = Recording::factory()->for($this->user)->pending()->create([
            'path' => 'recordings/1/test.m4a',
        ]);
        $result = app(TutorProcessor::class)->process($recording);
        expect($result->transcript)->toBe('Hello my name is Raj.')
            ->and($result->response)->toBe('Wah, great to meet you Raj yaar!')
            ->and($recording->fresh()->status->value)->toBe('completed')
            ->and($recording->fresh()->transcript)->toBe('Hello my name is Raj.');
        TutorAgent::assertPrompted(fn ($p) => $p->contains('Transcribe'));
    });
    it('marks recording as failed when Gemini throws', function () {
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');
        TutorAgent::fake(fn () => throw new RuntimeException('API error'));
        $recording = Recording::factory()->for($this->user)->pending()->create([
            'path' => 'recordings/1/test.m4a',
        ]);
        expect(fn () => app(TutorProcessor::class)->process($recording))
            ->toThrow(RuntimeException::class);
        expect($recording->fresh()->status->value)->toBe('failed');
    });
});
describe('ProcessRecording Listener', function () {
    it('dispatches AiResponseReady and GenerateTtsAudio on success', function () {
        Storage::fake('public');
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');
        Event::fake([AiResponseReady::class]);
        Queue::fake();
        TutorAgent::fake([
            ['transcript' => 'Test transcript', 'response' => 'Wah yaar!'],
        ]);
        $recording = Recording::factory()->pending()->create([
            'path' => 'recordings/1/test.m4a',
        ]);
        app(ProcessRecording::class)->handle(new RecordingFinished($recording));
        Event::assertDispatched(AiResponseReady::class);
        Queue::assertPushed(GenerateTtsAudio::class);
    });
    it('skips non-pending recordings', function () {
        TutorAgent::fake()->preventStrayPrompts();
        $recording = Recording::factory()->completed()->create();
        app(ProcessRecording::class)->handle(new RecordingFinished($recording));
        TutorAgent::assertNeverPrompted();
    });
});
```
---
## 7. As-Built Record

### Completed
- [x] `GEMINI_API_KEY` set in `.env`
- [x] `laravel/ai` migrations ran (`agent_conversations` + `agent_conversation_messages`)
- [x] `TutorAgent` in `app/Ai/Agents/` with `#[Provider(Lab::Gemini)]` + `#[Model('gemini-2.5-flash')]`
- [x] `RemembersConversations` trait — daily conversation scoping via `DB::table('agent_conversations')->whereDate(...)`
- [x] `Audio::fromStorage($recording->path, 'public')` — relative disk path
- [x] `$response['transcript']` + `$response['response']` via `StructuredAgentResponse` ArrayAccess
- [x] `AiResponseReady` broadcast on `PrivateChannel('user.{id}')` — channel already authorized in `channels.php`
- [x] `GenerateTtsAudio` stub dispatched to `tts` queue
- [x] `EventServiceProvider` wires `RecordingFinished → ProcessRecording` (extends `Illuminate\Foundation\Support\Providers\EventServiceProvider`)
- [x] Queue workers added to `docker/8.4/supervisord.conf` — `default` ×2, `ai` ×2, `scheduler` ×1
- [x] 4 Pest tests passing; `composer check` fully green

### Plan Deviations
| Original Plan | Actual Implementation | Reason |
|---|---|---|
| Step 8 used `bootstrap/app.php` `->withEvents()` | Used `EventServiceProvider` extending `Illuminate\Foundation\Support\Providers\EventServiceProvider` | AGENTS.md is the source of truth and explicitly requires this pattern |
| supervisord.conf not mentioned in VOICE-02 scope | Added queue workers (`default` ×2, `ai` ×2, `scheduler` ×1) | Without workers the `ai` queue never processes — pipeline would be silently broken |
---
## 8. Key Gotchas
| Gotcha | Detail |
|--------|--------|
| Namespace is `App\Ai` not `App\AI` | Artisan generates `app/Ai/Agents/` — match exactly in use statements |
| `continue()` uses named param | `->continue($id, as: $user)` — `as:` is required named argument |
| No `agent` column in `agent_conversations` | Filter by `user_id` + `whereDate` only — agent type not stored at conversation level |
| Larastan needs PHPDoc cast | `prompt()` returns `AgentResponse`; add `/** @var StructuredAgentResponse $response */` |
| `DB::table()` justified here | SDK exposes no Eloquent model for conversations — this is the only option |
| `TutorAgent::fake()` for structured output | Auto-generates fake data matching the schema — no manual array needed unless specific values required |
| `bootstrap/app.php` syntax | Laravel 13 `->withEvents(listen: [...])` — no `EventServiceProvider` class needed |
