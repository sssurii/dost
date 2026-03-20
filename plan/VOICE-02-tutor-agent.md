# VOICE-02: TutorAgent AI Integration

**Phase:** 3 — Voice Core  
**Complexity:** 4 | **Estimate:** 6h  
**Depends on:** VOICE-01 (Recording model + event), R&D-01 (audio format confirmed), INF-03 (`laravel/ai` SDK installed)  
**Blocks:** VOICE-03 (needs AI text response to generate TTS audio)

---

## 1. Objective

This is the **"Soul" of the app**. Build the `TutorAgent` that:
1. Receives a `RecordingFinished` event
2. Sends the audio file to `Gemini 2.5 Flash` via **`laravel/ai`** SDK
3. Gets back a structured `{transcript, response}` output — no regex parsing needed
4. Saves the response to the `Recording` model
5. Broadcasts an `AiResponseReady` event for VOICE-03 / Livewire UI

> **SDK:** `laravel/ai` v0.3.2 (official Laravel AI SDK, wraps `prism-php/prism` internally).  
> **Conversation history** is managed by the SDK's built-in `agent_conversations` / `agent_conversation_messages` tables.  
> **No custom `Conversation` or `Message` models are needed** — the SDK handles context windows.

---

## 2. The Tutor Persona

The system prompt is the most critical piece of product design in this entire app.

```
Core Values:
✅ Encouragement over correction
✅ Indian English warmth and idioms ("yaar", "achha", "wah!", "bilkul")
✅ Short responses — 2-3 sentences max (voice playback)
✅ Always ends with a follow-up question to keep the conversation going
✅ Responds to meaning and intent, never the words
❌ Never corrects grammar, pronunciation, or word choice — EVER
❌ Never says "That's wrong" or implies any error
❌ No formal tutoring language
```

---

## 3. Architecture Overview

```
RecordingFinished Event
        │
        ▼
ProcessRecording Listener (Queued on 'ai' queue)
        │
        ▼
TutorProcessor Service
    ├── Resolves today's conversation (daily-reset logic via RemembersConversations)
    ├── Loads audio path from storage
    ├── Calls (new TutorAgent)->forUser($user)->prompt(…, attachments: [Files\Audio::fromPath(…)])
    └── Receives structured {transcript, response} — no parsing needed
        │
        ▼
Recording::markAsCompleted(transcript, response)
        │
        ▼
AiResponseReady Event (Broadcast → Reverb → Livewire)
        │
        ▼
GenerateTtsAudio Job dispatched (→ VOICE-03)
```

---

## 4. Step-by-Step Implementation

### Step 1 — Install & Configure `laravel/ai`

> If completed in INF-03 (MCP Docs), skip the install — just verify the env variable.

```bash
composer require laravel/ai

php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"

# Runs migrations for agent_conversations + agent_conversation_messages tables
php artisan migrate
```

Update `.env`:

```dotenv
GEMINI_API_KEY=your-google-ai-studio-key
```

Get your key at: https://aistudio.google.com → **"Get API key"** → free tier is sufficient for MVP.

### Step 2 — Scaffold the TutorAgent

```bash
php artisan make:agent TutorAgent
# Creates: app/AI/Agents/TutorAgent.php
```

### Step 3 — Implement the `TutorAgent` Class

```php
<?php
// app/AI/Agents/TutorAgent.php

declare(strict_types=1);

namespace App\AI\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\RemembersConversations;

final class TutorAgent implements Agent, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;

    /**
     * The complete system prompt — the "soul" of Dost.
     * This is set server-side and cannot be overridden by user audio input.
     */
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
     * Structured output schema.
     * Gemini returns a typed JSON object with exactly these two fields.
     * No regex parsing needed.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transcript' => $schema->string()
                ->description('Verbatim transcription of what the user said in the audio.')
                ->required(),
            'response'   => $schema->string()
                ->description('Warm, encouraging 2-3 sentence Dost reply, ending with a follow-up question.')
                ->required(),
        ];
    }
}
```

### Step 4 — Create the `TutorResult` Value Object

```php
<?php
// app/Services/AI/TutorResult.php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Recording;

final readonly class TutorResult
{
    public function __construct(
        public string    $transcript,
        public string    $response,
        public Recording $recording,
    ) {}
}
```

### Step 5 — Create the `TutorProcessor` Service

This service orchestrates the recording → AI flow. It handles the daily conversation reset logic.

```bash
mkdir -p app/Services/AI
```

```php
<?php
// app/Services/AI/TutorProcessor.php

declare(strict_types=1);

namespace App\Services\AI;

use App\AI\Agents\TutorAgent;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files;

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
            $user      = $recording->user;
            $audioPath = $this->resolveAudioPath($recording);
            $agent     = $this->resolveAgentForUser($user);

            // Call Gemini with the audio file attached.
            // The agent returns the structured output array {transcript, response}.
            $result = $agent->prompt(
                'Transcribe the audio and then respond as Dost.',
                provider: Lab::Gemini,
                model: 'gemini-2.5-flash',
                attachments: [
                    Files\Audio::fromPath($audioPath),
                ],
            );

            $transcript    = $result['transcript'];
            $tutorResponse = $result['response'];

            $recording->markAsCompleted($transcript, $tutorResponse);

            Log::info('TutorProcessor: completed', [
                'recording_id' => $recording->id,
                'user_id'      => $recording->user_id,
            ]);

            return new TutorResult(
                transcript: $transcript,
                response:   $tutorResponse,
                recording:  $recording->fresh(),
            );

        } catch (\Throwable $e) {
            $recording->markAsFailed();

            Log::error('TutorProcessor: failed', [
                'recording_id' => $recording->id,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolve today's conversation or start a new one.
     *
     * Q7 answer: new conversation per day.
     * laravel/ai's RemembersConversations stores history in agent_conversations table.
     * We check if an existing conversation for this user+agent was started today.
     */
    private function resolveAgentForUser(User $user): TutorAgent
    {
        $agent = new TutorAgent;

        // Look for a conversation started today for this user + TutorAgent
        $todayConversation = $user->agentConversations()
            ->where('agent', TutorAgent::class)
            ->whereDate('created_at', today())
            ->latest()
            ->first();

        if ($todayConversation) {
            // Continue today's conversation (preserves context window)
            return $agent->forUser($user)->continue($todayConversation->id);
        }

        // Start fresh — new conversation for today
        return $agent->forUser($user);
    }

    /**
     * Resolve the absolute filesystem path to the audio file.
     *
     * @throws \RuntimeException if file is missing or too large for inline submission
     */
    private function resolveAudioPath(Recording $recording): string
    {
        $fullPath = Storage::disk('public')->path($recording->path);

        if (! file_exists($fullPath)) {
            throw new \RuntimeException(
                "Audio file not found for recording #{$recording->id}: {$recording->path}"
            );
        }

        // Gemini inline limit is ~20 MB. Recordings are typically <2 MB.
        $sizeBytes = filesize($fullPath);
        if ($sizeBytes > 20 * 1024 * 1024) {
            throw new \RuntimeException(
                "Audio file too large for inline submission ({$sizeBytes} bytes). " .
                'Implement the Gemini Files API for recordings > 20 MB.'
            );
        }

        return $fullPath;
    }
}
```

### Step 6 — Create the `AiResponseReady` Broadcast Event

```bash
php artisan make:event AiResponseReady
```

```php
<?php
// app/Events/AiResponseReady.php

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

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recording->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'recording.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'recording_id'  => $this->recording->id,
            'transcript'    => $this->recording->transcript,
            'response_text' => $this->recording->ai_response_text,
            // audio_url is null here; populated by VOICE-03 after TTS generation
            'audio_url'     => null,
        ];
    }
}
```

### Step 7 — Implement the `ProcessRecording` Listener

```php
<?php
// app/Listeners/ProcessRecording.php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AiResponseReady;
use App\Events\RecordingFinished;
use App\Jobs\GenerateTtsAudio;
use App\Services\AI\TutorProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class ProcessRecording implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue   = 'ai';
    public int    $tries   = 2;
    public int    $timeout = 30; // 30s latency guardrail
    public int    $backoff = 5;

    public function __construct(
        private readonly TutorProcessor $processor,
    ) {}

    public function handle(RecordingFinished $event): void
    {
        $recording = $event->recording;

        if (! $recording->isPending()) {
            return; // Idempotency guard
        }

        $result = $this->processor->process($recording);

        // Broadcast so UI shows AI response text immediately
        AiResponseReady::dispatch($result->recording);

        // Dispatch TTS job to separate queue — doesn't block AI queue
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

### Step 8 — Register Listener & Configure Queue

**`EventServiceProvider` (or `bootstrap/app.php` in Laravel 12+):**

```php
use App\Events\RecordingFinished;
use App\Listeners\ProcessRecording;

protected $listen = [
    RecordingFinished::class => [
        ProcessRecording::class,
    ],
];
```

**`.env` additions:**

```dotenv
GEMINI_API_KEY=your-google-ai-studio-key

# Dev Docker: database queue (Valkey optional for production)
QUEUE_CONNECTION=database

# Broadcast
BROADCAST_CONNECTION=reverb
```

**`supervisord.conf`** — add the AI queue worker:

```ini
[program:laravel-queue-ai]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=ai,default --sleep=3 --tries=2 --timeout=30
autostart=true
autorestart=true
user=sail
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-ai.log
```

> **On Android device (NativePHP):** Queue uses `database` driver (SQLite). The `QUEUE_CONNECTION=database` in `.env.mobile` handles this — no external queue server needed.

**Broadcast channel auth** — `routes/channels.php`:

```php
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

---

## 5. Pest Tests

Create `tests/Feature/Voice/TutorProcessorTest.php`:

```php
<?php

use App\AI\Agents\TutorAgent;
use App\Events\AiResponseReady;
use App\Events\RecordingFinished;
use App\Jobs\GenerateTtsAudio;
use App\Listeners\ProcessRecording;
use App\Models\Recording;
use App\Models\User;
use App\Services\AI\TutorProcessor;
use App\Services\AI\TutorResult;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('TutorProcessor', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        Storage::fake('public');
    });

    it('marks recording completed with transcript and response', function () {
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');

        $recording = Recording::factory()->create([
            'user_id' => $this->user->id,
            'path'    => 'recordings/1/test.m4a',
            'status'  => 'pending',
        ]);

        // Mock the laravel/ai TutorAgent
        $mockAgent = Mockery::mock(TutorAgent::class);
        $mockAgent->shouldReceive('forUser')->andReturnSelf();
        $mockAgent->shouldReceive('prompt')->andReturn([
            'transcript' => 'Hello, my name is Raj.',
            'response'   => 'Wah! Great to meet you Raj yaar! What do you like to talk about?',
        ]);

        $this->app->instance(TutorAgent::class, $mockAgent);

        $processor = $this->app->make(TutorProcessor::class);
        $result    = $processor->process($recording);

        expect($result->transcript)->toBe('Hello, my name is Raj.')
            ->and($result->response)->toContain('Raj')
            ->and($recording->fresh()->status)->toBe('completed')
            ->and($recording->fresh()->transcript)->toBe('Hello, my name is Raj.');
    });

    it('marks recording as failed when Gemini throws', function () {
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');

        $recording = Recording::factory()->create([
            'user_id' => $this->user->id,
            'path'    => 'recordings/1/test.m4a',
            'status'  => 'pending',
        ]);

        $mockAgent = Mockery::mock(TutorAgent::class);
        $mockAgent->shouldReceive('forUser')->andReturnSelf();
        $mockAgent->shouldReceive('prompt')->andThrow(new \RuntimeException('API error'));

        $this->app->instance(TutorAgent::class, $mockAgent);

        $processor = $this->app->make(TutorProcessor::class);

        expect(fn () => $processor->process($recording))
            ->toThrow(\RuntimeException::class);

        expect($recording->fresh()->status)->toBe('failed');
    });

    it('throws when audio file is missing', function () {
        $recording = Recording::factory()->create([
            'user_id' => $this->user->id,
            'path'    => 'recordings/1/missing.m4a',
            'status'  => 'pending',
        ]);

        $processor = $this->app->make(TutorProcessor::class);

        expect(fn () => $processor->process($recording))
            ->toThrow(\RuntimeException::class, 'Audio file not found');
    });
});

describe('ProcessRecording Listener', function () {

    it('dispatches AiResponseReady and GenerateTtsAudio on success', function () {
        Storage::fake('public');
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');
        Event::fake([AiResponseReady::class]);
        Queue::fake();

        $user      = User::factory()->create();
        $recording = Recording::factory()->create([
            'user_id' => $user->id,
            'path'    => 'recordings/1/test.m4a',
            'status'  => 'pending',
        ]);

        $mockProcessor = Mockery::mock(TutorProcessor::class);
        $mockProcessor->shouldReceive('process')->andReturn(
            new TutorResult('Test transcript', 'Wah!', $recording)
        );
        $this->app->instance(TutorProcessor::class, $mockProcessor);

        $listener = $this->app->make(ProcessRecording::class);
        $listener->handle(new RecordingFinished($recording));

        Event::assertDispatched(AiResponseReady::class);
        Queue::assertPushedOn('tts', GenerateTtsAudio::class);
    });

    it('skips non-pending recordings', function () {
        $recording = Recording::factory()->create(['status' => 'completed']);

        $mockProcessor = Mockery::mock(TutorProcessor::class);
        $mockProcessor->shouldNotReceive('process');
        $this->app->instance(TutorProcessor::class, $mockProcessor);

        $listener = $this->app->make(ProcessRecording::class);
        $listener->handle(new RecordingFinished($recording));
    });
});
```

---

## 6. Verification Checklist

- [ ] `laravel/ai` installed and `agent_conversations` / `agent_conversation_messages` tables migrated
- [ ] `TutorAgent` scaffolded via `php artisan make:agent TutorAgent`
- [ ] System prompt returns correct persona instructions (verify with a test prompt)
- [ ] Structured output `{transcript, response}` correctly returned — no regex needed
- [ ] `Recording` updated with `status=completed`, `transcript`, and `ai_response_text`
- [ ] Daily conversation reset: new conversation created for a new day
- [ ] Same-day conversation continues with context preserved
- [ ] `AiResponseReady` broadcast event fires after completion
- [ ] `GenerateTtsAudio` job dispatched to `tts` queue
- [ ] Queue worker processes `ai` queue with 30s timeout
- [ ] Failed jobs mark recording as `failed` after 2 retries
- [ ] `composer test` passes all TutorProcessor tests (with mocked agent)

---

## 7. Acceptance Criteria

1. `TutorProcessor::process()` sends audio to Gemini and returns a warm, structured response.
2. System prompt is set server-side — cannot be overridden by user speech.
3. Structured output provides clean `transcript` and `response` fields.
4. Conversation context is maintained within the same day; resets at midnight.
5. AI response saved to `recordings.ai_response_text` within 3 seconds of `RecordingFinished`.
6. Queue handles failures gracefully; recording marked `failed` after 2 retries.
7. `AiResponseReady` event broadcast within **3 seconds** of `RecordingFinished`.

---

## 8. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| `laravel/ai` `Files\Audio` doesn't support `audio/mp4` | Verify in R&D-01; if fails, use `Files\Document` with audio MIME or raw Gemini REST API |
| Audio file > 20 MB inline limit | Throw descriptive exception; implement Gemini File API upload for large recordings |
| Gemini structured output returns invalid JSON | `laravel/ai` handles retries internally; add defensive validation in `TutorProcessor` |
| Gemini prompt injection via user audio | System prompt is server-side only; user content is binary audio, not text |
| AI response too long for TTS | Prompt instructs Gemini to keep responses ≤ 3 sentences; VOICE-03 caps at 500 chars |
| `agent_conversations` table grows unbounded | Schedule `php artisan model:prune` on `AgentConversation` (30-day TTL) in DATA-01 |
| Daily reset edge case at midnight | Use `whereDate('created_at', today())` — server timezone set via `APP_TIMEZONE` in `.env` |
| `nativephp/mobile` + Laravel 13 queue compatibility | On device, queue uses SQLite (`database` driver) — no Valkey/Redis dependency |
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title')->nullable(); // Auto-generated from first exchange
    $table->unsignedInteger('total_duration_seconds')->default(0);
    $table->timestamps();

    $table->index('user_id');
});
```

```php
// xxxx_create_messages_table.php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('recording_id')->nullable()->constrained()->nullOnDelete();

    // 'user' = spoken by user, 'assistant' = Dost's response
    $table->enum('role', ['user', 'assistant']);

    $table->text('content'); // text content for context window
    $table->string('audio_path')->nullable(); // path to audio file
    $table->timestamps();

    $table->index(['conversation_id', 'created_at']);
});
```

