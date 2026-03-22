# VOICE-01: Hold-to-Speak Recording Logic

**Phase:** 3 — Voice Core  
**Complexity:** 4 | **Estimate:** 5h  
**Depends on:** MOB-01 (NativePHP + Microphone), AUTH-01 (authenticated user), R&D-01 (audio format confirmed)  
**Blocks:** VOICE-02 (needs recording file to process)

---

## 1. Objective

Build the **Hold-to-Speak (walkie-talkie) UX** as a Livewire component. User presses-and-holds a mic button → recording starts → release → recording saved → `RecordingFinished` event dispatched → processing begins.

---

## 2. UX Flow

```
User lands on Dashboard
        │
        ▼
  [🎙️ Hold to Speak]   ← Resting state, mic button visible
        │
   [Press & Hold]
        │
        ▼
  [🔴 Recording...]    ← Animated pulsing, recording in progress
        │
   [Release]
        │
        ▼
  [⏳ Processing...]   ← AI is thinking, mic disabled
        │
        ▼
  [🔊 Dost is speaking]← AI audio playing back
        │
        ▼
  [🎙️ Hold to Speak]   ← Back to resting state
```

---

## 3. Component Architecture

```
app/Livewire/
└── Voice/
    └── RecordingButton.php        ← Main Livewire component

resources/views/livewire/voice/
└── recording-button.blade.php    ← View (wire:ignore for mic button)

app/Events/
└── RecordingFinished.php         ← Broadcast event

app/Listeners/
└── ProcessRecording.php          ← Queued listener

app/Models/
└── Recording.php                 ← Eloquent model

database/migrations/
└── xxxx_create_recordings_table.php
```

---

## 4. Step-by-Step Implementation

### Step 1 — Create the `recordings` Table

```bash
php artisan make:migration create_recordings_table
```

```php
<?php
// database/migrations/xxxx_create_recordings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Relative path from storage/app/public/
            // e.g. recordings/42/rec_1234567890.m4a
            $table->string('path');

            // Audio metadata
            $table->string('mime_type')->default('audio/mp4');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();

            // Processing state machine
            // pending → processing → completed → failed
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending');

            // AI response (set by VOICE-02)
            $table->text('transcript')->nullable();
            $table->text('ai_response_text')->nullable();
            $table->string('ai_response_audio_path')->nullable();

            // Auto-cleanup support (DATA-01)
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
```

### Step 2 — Create the `Recording` Model

```bash
php artisan make:model Recording
```

```php
<?php
// app/Models/Recording.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class Recording extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'mime_type',
        'duration_seconds',
        'file_size_bytes',
        'status',
        'transcript',
        'ai_response_text',
        'ai_response_audio_path',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk('public')->path($this->path);
    }

    public function getPublicUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(string $transcript, string $aiResponse): void
    {
        $this->update([
            'status' => 'completed',
            'transcript' => $transcript,
            'ai_response_text' => $aiResponse,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
```

### Step 3 — Create the `RecordingFinished` Event

```bash
php artisan make:event RecordingFinished
```

```php
<?php
// app/Events/RecordingFinished.php

declare(strict_types=1);

namespace App\Events;

use App\Models\Recording;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RecordingFinished implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Recording $recording,
    ) {}

    /**
     * Broadcast on the authenticated user's private channel.
     * Livewire component listens on this channel for UI state updates.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recording->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'recording.finished';
    }

    public function broadcastWith(): array
    {
        return [
            'recording_id' => $this->recording->id,
            'status' => $this->recording->status,
        ];
    }
}
```

### Step 4 — Create the Livewire Component

```bash
php artisan make:livewire Voice/RecordingButton
```

```php
<?php
// app/Livewire/Voice/RecordingButton.php

declare(strict_types=1);

namespace App\Livewire\Voice;

use App\Events\RecordingFinished;
use App\Models\Recording;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
final class RecordingButton extends Component
{
    // UI state machine: idle | recording | processing | playing | error
    public string $uiState = 'idle';

    public ?int $currentRecordingId = null;

    public string $statusMessage = 'Hold to speak';

    public function mount(): void
    {
        $this->uiState = 'idle';
    }

    /**
     * Called from JavaScript when NativePHP recording starts.
     */
    public function onRecordingStarted(): void
    {
        $this->uiState = 'recording';
        $this->statusMessage = 'Listening...';
    }

    /**
     * Called from JavaScript when NativePHP recording stops.
     * $nativePath is the device-local path returned by the native plugin.
     */
    public function onRecordingStopped(string $nativePath): void
    {
        try {
            $this->uiState = 'processing';
            $this->statusMessage = 'Dost is thinking...';

            // Move from native temp path to Laravel storage
            $recording = $this->persistRecording($nativePath);

            $this->currentRecordingId = $recording->id;

            // Fire event — VOICE-02 listener will pick this up
            RecordingFinished::dispatch($recording);

        } catch (\Throwable $e) {
            Log::error('Recording persist failed', [
                'user_id' => Auth::id(),
                'path' => $nativePath,
                'error' => $e->getMessage(),
            ]);

            $this->uiState = 'error';
            $this->statusMessage = 'Something went wrong. Try again!';
        }
    }

    /**
     * Called via Reverb broadcast when AI processing completes.
     * VOICE-02 fires this.
     */
    #[On('echo-private:user.{auth.id},recording.completed')]
    public function onAiResponseReady(array $event): void
    {
        $this->uiState = 'playing';
        $this->statusMessage = 'Dost is speaking...';

        // Dispatch to JS to trigger audio playback
        $recording = Recording::find($event['recording_id']);

        if ($recording) {
            $this->dispatch('play-ai-response', [
                'audioUrl' => $recording->ai_response_audio_path
                    ? Storage::disk('public')->url($recording->ai_response_audio_path)
                    : null,
                'text' => $recording->ai_response_text,
            ]);
        }
    }

    /**
     * Called from JS when AI audio playback finishes.
     */
    public function onPlaybackFinished(): void
    {
        $this->uiState = 'idle';
        $this->statusMessage = 'Hold to speak';
        $this->currentRecordingId = null;
    }

    private function persistRecording(string $nativePath): Recording
    {
        $userId = Auth::id();
        $filename = 'rec_' . $userId . '_' . time() . '.m4a';
        $storagePath = "recordings/{$userId}/{$filename}";

        // Create directory if needed
        Storage::disk('public')->makeDirectory("recordings/{$userId}");

        // Copy from native device path to Laravel public storage
        $fileContents = file_get_contents($nativePath);

        if ($fileContents === false) {
            throw new \RuntimeException("Cannot read native audio file: {$nativePath}");
        }

        Storage::disk('public')->put($storagePath, $fileContents);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Recording::create([
            'user_id' => $userId,
            'path' => $storagePath,
            'mime_type' => 'audio/mp4',
            'file_size_bytes' => strlen($fileContents),
            'status' => 'pending',
            'expires_at' => now()->addDays($user->audio_retention_days ?? 2),
        ]);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.voice.recording-button');
    }
}
```

### Step 5 — Create the Blade View

Create `resources/views/livewire/voice/recording-button.blade.php`:

```html
<div class="min-h-screen bg-gray-950 flex flex-col items-center justify-between px-6 pb-12 pt-8">

    {{-- Top: Conversation area (will be expanded in VOICE-02/03) --}}
    <div class="flex-1 w-full max-w-sm flex items-center justify-center">
        <div class="text-center">
            @if ($uiState === 'idle')
                <div class="text-gray-600 text-sm">
                    Tap and hold the mic to start speaking
                </div>
            @elseif ($uiState === 'recording')
                {{-- Pulsing waveform indicator --}}
                <div class="flex items-center justify-center gap-1 h-12">
                    @foreach(range(1, 5) as $bar)
                        <div class="w-1.5 bg-rose-500 rounded-full animate-pulse"
                             style="height: {{ rand(12, 44) }}px;
                                    animation-delay: {{ ($bar - 1) * 0.15 }}s;">
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-rose-400 text-sm font-medium">Recording...</p>
            @elseif ($uiState === 'processing')
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full border-2 border-orange-500/30
                                border-t-orange-500 animate-spin">
                    </div>
                    <p class="text-orange-400 text-sm">Dost is thinking...</p>
                </div>
            @elseif ($uiState === 'playing')
                {{-- Audio waveform / playing indicator --}}
                <div class="flex items-center justify-center gap-1 h-12">
                    @foreach(range(1, 7) as $bar)
                        <div class="w-1.5 bg-green-500 rounded-full"
                             style="height: {{ rand(8, 40) }}px;
                                    animation: bounce 0.6s {{ ($bar - 1) * 0.1 }}s infinite alternate;">
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-green-400 text-sm">Dost is speaking...</p>
            @elseif ($uiState === 'error')
                <div class="flex flex-col items-center gap-2">
                    <div class="w-12 h-12 rounded-full bg-rose-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-rose-400" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <p class="text-rose-400 text-sm">{{ $statusMessage }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Bottom: Mic Button --}}
    <div class="flex flex-col items-center gap-4">

        <p class="text-gray-500 text-xs">{{ $statusMessage }}</p>

        {{--
            wire:ignore is CRITICAL here.
            NativePHP handles all touch events natively.
            Livewire must NOT re-render this button during recording.
        --}}
        <div wire:ignore>
            <button
                id="mic-button"
                type="button"
                class="
                    w-24 h-24 rounded-full
                    flex items-center justify-center
                    shadow-2xl transition-all duration-150
                    select-none touch-none
                    {{ $uiState === 'idle'
                        ? 'bg-gradient-to-br from-orange-500 to-rose-500 shadow-orange-500/25 active:scale-95'
                        : '' }}
                    {{ $uiState === 'recording'
                        ? 'bg-rose-500 ring-4 ring-rose-500/30 scale-110'
                        : '' }}
                    {{ in_array($uiState, ['processing', 'playing'])
                        ? 'bg-gray-800 opacity-50 cursor-not-allowed'
                        : '' }}
                    {{ $uiState === 'error'
                        ? 'bg-gray-800'
                        : '' }}
                "
                @disabled($uiState === 'processing' || $uiState === 'playing')
            >
                {{-- Idle: microphone icon --}}
                @if ($uiState !== 'recording')
                    <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M9 5a3 3 0 016 0v6a3 3 0 01-6 0V5z"/>
                    </svg>
                @else
                    {{-- Recording: stop/square icon --}}
                    <div class="w-8 h-8 bg-white rounded-md"></div>
                @endif
            </button>
        </div>

        <p class="text-gray-600 text-xs">
            @if ($uiState === 'idle') Tap and hold @endif
            @if ($uiState === 'recording') Release to send @endif
        </p>
    </div>

    {{-- Hidden audio player for AI response (VOICE-03) --}}
    <audio id="ai-response-player" class="hidden"></audio>
</div>

@script
<script>
    // ─── NativePHP Bridge ────────────────────────────────────────────────
    const micButton = document.getElementById('mic-button');
    let isRecording = false;

    // Touch start → start recording
    micButton.addEventListener('touchstart', (e) => {
        e.preventDefault(); // prevent double-tap zoom
        if (micButton.disabled) return;

        isRecording = true;

        // Call NativePHP native recording API
        if (typeof Native !== 'undefined' && Native.Microphone) {
            Native.Microphone.start();
        }

        // Notify Livewire
        $wire.onRecordingStarted();
    }, { passive: false });

    // Touch end → stop recording
    micButton.addEventListener('touchend', (e) => {
        e.preventDefault();
        if (!isRecording) return;

        isRecording = false;

        if (typeof Native !== 'undefined' && Native.Microphone) {
            // stop() is async; callback receives the file path
            Native.Microphone.stop((filePath) => {
                $wire.onRecordingStopped(filePath);
            });
        } else {
            // Development fallback: simulate with a fake path
            console.warn('NativePHP Microphone not available (browser preview)');
            setTimeout(() => {
                $wire.onRecordingStopped('/tmp/fake-recording.m4a');
            }, 500);
        }
    }, { passive: false });

    // Touch cancel (e.g., user moves finger away)
    micButton.addEventListener('touchcancel', (e) => {
        if (!isRecording) return;
        isRecording = false;

        if (typeof Native !== 'undefined' && Native.Microphone) {
            Native.Microphone.stop((filePath) => {
                if (filePath) $wire.onRecordingStopped(filePath);
            });
        }
    });

    // ─── AI Response Playback (VOICE-03 hook) ───────────────────────────
    $wire.on('play-ai-response', ({ audioUrl, text }) => {
        const player = document.getElementById('ai-response-player');

        if (audioUrl) {
            player.src = audioUrl;
            player.play().catch((err) => {
                console.error('Audio playback failed:', err);
                $wire.onPlaybackFinished();
            });

            player.onended = () => {
                $wire.onPlaybackFinished();
            };
        } else if (text) {
            // TTS fallback: use Web Speech API if no audio file
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'en-IN'; // Indian English locale
            utterance.rate = 0.9;
            utterance.onend = () => {
                $wire.onPlaybackFinished();
            };
            window.speechSynthesis.speak(utterance);
        } else {
            $wire.onPlaybackFinished();
        }
    });
</script>
@endscript
```

### Step 6 — Register the Event Listener

```bash
php artisan make:listener ProcessRecording --event=RecordingFinished
```

Wire it in `app/Providers/EventServiceProvider.php` (or via auto-discovery):

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \App\Events\RecordingFinished::class => [
        \App\Listeners\ProcessRecording::class,
    ],
];
```

The actual listener body is implemented in **VOICE-02**.

### Step 7 — Add Dashboard Route

```php
// routes/web.php
use App\Livewire\Voice\RecordingButton;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', RecordingButton::class)->name('dashboard');
});
```

### Step 8 — Run Migration

```bash
php artisan migrate
```

---

## 5. Pest Tests

Create `tests/Feature/Voice/RecordingButtonTest.php`:

```php
<?php

use App\Events\RecordingFinished;
use App\Livewire\Voice\RecordingButton;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('RecordingButton Component', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        Storage::fake('public');
        Event::fake([RecordingFinished::class]);
    });

    it('renders in idle state for authenticated user', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->assertSet('uiState', 'idle')
            ->assertSee('Hold to speak');
    });

    it('transitions to recording state on start', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStarted')
            ->assertSet('uiState', 'recording');
    });

    it('transitions to processing and fires event on stop', function () {
        // Create a fake audio file
        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStarted')
            ->call('onRecordingStopped', $fakePath)
            ->assertSet('uiState', 'processing');

        Event::assertDispatched(RecordingFinished::class);
    });

    it('returns to idle after playback finishes', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->call('onPlaybackFinished')
            ->assertSet('uiState', 'idle');
    });

    it('recording is persisted with correct user and expiry', function () {
        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStopped', $fakePath);

        $this->assertDatabaseHas('recordings', [
            'user_id' => $this->user->id,
            'status' => 'pending',
            'mime_type' => 'audio/mp4',
        ]);
    });
});
```

---

## 6. Verification Checklist

- [ ] `recordings` table migrated successfully
- [ ] `Recording` model accessible, `expires_at` set correctly
- [ ] Livewire component renders at `/dashboard`
- [ ] `wire:ignore` present on the mic button wrapper
- [ ] `onRecordingStarted()` transitions state to `recording`
- [ ] `onRecordingStopped()` saves file to `storage/app/public/recordings/{user_id}/`
- [ ] `RecordingFinished` event dispatched after stop
- [ ] UI disables mic button in `processing` and `playing` states
- [ ] `composer test` passes all component tests

---

## 7. Acceptance Criteria

1. User can see the mic button at `/dashboard` after login.
2. Pressing the button triggers visual recording state (pulsing animation).
3. Releasing saves the audio to `storage/app/public/recordings/{user_id}/` with correct filename pattern.
4. A `Recording` record is created in DB with `status=pending` and `expires_at` based on user preference.
5. `RecordingFinished` event is dispatched and VOICE-02 listener can receive it.
6. Mic button is disabled while AI is processing or playing.

---

## 8. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| NativePHP `Microphone.stop()` callback timing | Use `touchend` with promise-based callback; add 300ms debounce |
| Large audio files blocking the HTTP request | Move file copy to a queued job if > 5MB |
| `wire:ignore` breaking Livewire state sync | Keep native elements inside `wire:ignore`; all state on component properties |
| Reverb not running during dev | Fallback: use `sync` driver for events in dev, Reverb in prod |
| Android back gesture during recording | Listen for `visibilitychange` event; auto-stop recording if app goes to background |

