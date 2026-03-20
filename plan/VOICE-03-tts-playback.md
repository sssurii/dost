# VOICE-03: AI Response & TTS Playback

**Phase:** 3 — Voice Core  
**Complexity:** 3 | **Estimate:** 4h  
**Depends on:** VOICE-02 (AI text response in `recordings.ai_response_text`), MOB-01 (NativePHP WebView)  
**Blocks:** Nothing — this closes the voice loop

---

## 1. Objective

Close the voice loop by:
1. Playing the AI's text response as speech after `AiResponseReady` is broadcast
2. Disabling the mic button for the entire duration of playback (MVP constraint)
3. Returning to idle state when playback finishes

---

## 2. TTS Strategy (Q2 Resolved)

**Two-tier approach: free first, quality upgrade later — always through `laravel/ai`.**

| Tier | Provider | When | Cost |
|------|----------|------|------|
| **Tier 1 — MVP** | Web Speech API (`window.speechSynthesis`) | Right now — validation stage | Free |
| **Tier 2 — Upgrade** | OpenAI TTS via `laravel/ai` (`tts-1`, voice: `nova`) | When voice quality is a user retention issue | Pay-per-char |

> **Rule:** If any AI service is ever needed for TTS, it goes **exclusively through `laravel/ai`**.  
> Direct calls to any vendor TTS SDK (Google Cloud TTS, ElevenLabs, etc.) are not in scope.

### Why Web Speech API first

- Zero server cost — no API key, no quota, no infra
- Works natively in NativePHP's Android WebView (API 29+) via the device's built-in Google TTS engine
- `en-IN` locale is supported on all Android devices with Google TTS installed (99%+)
- Frontend is **pre-wired** for server-generated audio — upgrading to Tier 2 requires only a backend job and a `.env` key (see Section 6)

---

## 3. Architecture Overview

### Tier 1 — MVP (Web Speech API)

```
AiResponseReady broadcast  (VOICE-02)
  channel: user.{id}  |  event: recording.completed
        │
        ▼
RecordingButton  [Livewire]
  #[On('echo-private:user.{auth.id},recording.completed')]
        │
        ▼
$this->dispatch('play-ai-response', [ text: "...", audioUrl: null ])
        │
        ▼
JS: speakWithWebSpeech(text)
  window.speechSynthesis.speak(utterance)
  lang: en-IN  |  rate: 0.88  |  Indian voice if available
        │
        ▼
utterance.onend → $wire.call('onPlaybackFinished')
        │
        ▼
uiState = 'idle'  →  mic button re-enabled
```

### Tier 2 — Upgrade (OpenAI TTS via `laravel/ai`)

```
AiResponseReady broadcast  (VOICE-02)
        │
        ▼
GenerateTtsAudio Job  (queued on 'tts' queue)
        │
        ▼
TextToSpeechService
  Ai::speech()->using(Lab::OpenAi, 'tts-1')->voice('nova')->generate($text)
        │
        ▼
MP3 saved to storage  →  recordings.ai_response_audio_path populated
        │
        ▼
AiAudioReady broadcast  →  { audioUrl: "https://…/response.mp3", text: "…" }
        │
        ▼
JS: playAudioFile(audioUrl, fallbackText)
  <audio> element plays MP3
  on error → falls back to speakWithWebSpeech(fallbackText)
        │
        ▼
player.onended → $wire.call('onPlaybackFinished')
```

---

## 4. MVP Implementation — Web Speech API

### Step 1 — Handle `AiResponseReady` in the Livewire Component

Add to `app/Livewire/Voice/RecordingButton.php` (built in VOICE-01):

```php
use Livewire\Attributes\On;

// Called when VOICE-02 broadcasts AiResponseReady (event alias: recording.completed)
#[On('echo-private:user.{auth.id},recording.completed')]
public function onAiResponseReady(array $event): void
{
    $this->uiState       = 'playing';
    $this->statusMessage = 'Dost is speaking...';

    $this->dispatch('play-ai-response', [
        'text'     => $event['response_text'] ?? '',
        'audioUrl' => $event['audio_url'] ?? null,
        // audioUrl is null in Tier 1 (Web Speech path).
        // Non-null after Tier 2 upgrade — JS handles both automatically.
    ]);
}

public function onPlaybackFinished(): void
{
    $this->uiState       = 'idle';
    $this->statusMessage = 'Hold to speak';
}
```

### Step 2 — Add the `<audio>` Element and JavaScript Handler

Add to `resources/views/livewire/voice/recording-button.blade.php`:

```html
{{--
    Hidden <audio> element.
    Unused in Tier 1 (Web Speech path). Pre-wired for Tier 2 (OpenAI TTS MP3 playback).
--}}
<audio id="ai-response-player" class="hidden" preload="none"></audio>

@script
<script>
/**
 * VOICE-03: TTS Playback dispatcher
 *
 * Tier 1 (MVP):     Web Speech API  — free, on-device, zero server calls
 * Tier 2 (Upgrade): laravel/ai OpenAI TTS — server-generated MP3
 *
 * The audioUrl payload determines which tier is used.
 * No frontend code change is needed when upgrading from Tier 1 to Tier 2.
 */
$wire.on('play-ai-response', ({ text, audioUrl }) => {
    if (audioUrl) {
        playAudioFile(audioUrl, text);    // Tier 2: server-generated MP3
    } else if (text) {
        speakWithWebSpeech(text);         // Tier 1: free on-device TTS
    } else {
        $wire.call('onPlaybackFinished'); // Nothing to play — unblock mic
    }
});

// ─── Tier 1: Web Speech API ─────────────────────────────────────────────────

function speakWithWebSpeech(text) {
    if (!window.speechSynthesis) {
        console.warn('[VOICE-03] Web Speech API unavailable — unblocking mic');
        $wire.call('onPlaybackFinished');
        return;
    }

    window.speechSynthesis.cancel(); // Clear any queued speech

    const utterance  = new SpeechSynthesisUtterance(text);
    utterance.lang   = 'en-IN';
    utterance.rate   = 0.88;   // Slightly slower — easier for learners to follow
    utterance.pitch  = 1.05;
    utterance.volume = 1.0;

    utterance.onend  = () => $wire.call('onPlaybackFinished');
    utterance.onerror = (e) => {
        console.error('[VOICE-03] speechSynthesis error:', e);
        $wire.call('onPlaybackFinished'); // Always unblock mic on error
    };

    // Select Indian English voice if available.
    // Android 10+ ships Google TTS engine with en-IN voices pre-installed.
    function applyVoiceAndSpeak() {
        const voices = window.speechSynthesis.getVoices();
        const indianVoice = voices.find(
            v => v.lang === 'en-IN' || v.name.toLowerCase().includes('india')
        );
        if (indianVoice) utterance.voice = indianVoice;
        window.speechSynthesis.speak(utterance);
    }

    // Voices may load asynchronously on first call (Android WebView quirk)
    if (window.speechSynthesis.getVoices().length > 0) {
        applyVoiceAndSpeak();
    } else {
        window.speechSynthesis.onvoiceschanged = () => {
            window.speechSynthesis.onvoiceschanged = null;
            applyVoiceAndSpeak();
        };
    }
}

// ─── Tier 2: Server-generated MP3 (laravel/ai OpenAI TTS upgrade) ───────────

function playAudioFile(audioUrl, fallbackText) {
    const player = document.getElementById('ai-response-player');
    player.src   = audioUrl;
    player.load();

    player.onended = () => $wire.call('onPlaybackFinished');

    player.play().catch((err) => {
        console.warn('[VOICE-03] MP3 playback failed — falling back to Web Speech:', err);
        // Graceful degradation: if the audio file fails, speak via Web Speech
        if (fallbackText) {
            speakWithWebSpeech(fallbackText);
        } else {
            $wire.call('onPlaybackFinished');
        }
    });
}
</script>
@endscript
```

### Step 3 — Update UI State Bindings

Update status text and button state in `recording-button.blade.php`:

```html
{{-- Status text — includes the 'playing' state --}}
<p class="text-sm text-gray-400 min-h-[1.25rem] text-center">
    @if ($uiState === 'idle')        Hold the button and speak
    @elseif ($uiState === 'recording')  🔴 Recording...
    @elseif ($uiState === 'processing') ⏳ Dost is thinking...
    @elseif ($uiState === 'playing')    🔊 Dost is speaking...
    @elseif ($uiState === 'error')      Something went wrong. Try again.
    @endif
</p>

{{-- Mic button — disabled during processing AND playing --}}
<button
    id="mic-button"
    wire:ignore
    @disabled(in_array($uiState, ['processing', 'playing']))
    class="
        w-28 h-28 rounded-full flex items-center justify-center
        text-4xl transition-all duration-150 select-none
        {{ in_array($uiState, ['processing', 'playing'])
            ? 'bg-gray-700 opacity-50 cursor-not-allowed'
            : 'bg-gradient-to-br from-orange-500 to-rose-500
               shadow-lg shadow-orange-500/30 active:scale-95' }}
    "
>
    {{ match($uiState) {
        'idle'       => '🎙️',
        'recording'  => '🔴',
        'processing' => '⏳',
        'playing'    => '🔊',
        default      => '🎙️',
    } }}
</button>
```

### Step 4 — Smoke-Test on Device

Verify Web Speech API works in the NativePHP WebView before full integration testing:

```javascript
// Run in browser DevTools or NativePHP WebView debugger:

// 1. Quick speech test
const u = new SpeechSynthesisUtterance('Wah! Testing one two three, yaar!');
u.lang = 'en-IN';
window.speechSynthesis.speak(u);
// Expected: device speaks with Indian English accent

// 2. Check en-IN voices are available
console.table(
    window.speechSynthesis.getVoices().filter(v => v.lang.startsWith('en-IN'))
);
// Expected: at least one voice entry
```

> **If `en-IN` is missing:** the device will fall back to its default TTS voice. The response still plays — just without the Indian accent. On a physical Android device with Google TTS installed this will not occur.

---

## 5. Pest Tests

```php
<?php
// tests/Feature/Voice/TtsPlaybackTest.php

use App\Livewire\Voice\RecordingButton;
use App\Models\Recording;
use App\Models\User;
use Livewire\Livewire;

describe('VOICE-03: TTS Playback', function () {

    it('transitions to playing state when AiResponseReady is received (Tier 1 path)', function () {
        $user      = User::factory()->create();
        $recording = Recording::factory()->create([
            'user_id'          => $user->id,
            'ai_response_text' => 'Wah! Great to meet you Raj yaar!',
            'status'           => 'completed',
        ]);

        Livewire::actingAs($user)
            ->test(RecordingButton::class)
            ->call('onAiResponseReady', [
                'recording_id'  => $recording->id,
                'response_text' => 'Wah! Great to meet you Raj yaar!',
                'audio_url'     => null, // Tier 1: no server audio
            ])
            ->assertSet('uiState', 'playing')
            ->assertSet('statusMessage', 'Dost is speaking...')
            ->assertDispatched('play-ai-response', function (string $event, array $params) {
                return $params['text'] === 'Wah! Great to meet you Raj yaar!'
                    && $params['audioUrl'] === null;
            });
    });

    it('returns to idle state after onPlaybackFinished is called', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->call('onPlaybackFinished')
            ->assertSet('uiState', 'idle')
            ->assertSet('statusMessage', 'Hold to speak');
    });

    it('mic button shows disabled state while playing', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->assertSee('cursor-not-allowed');
    });

    it('mic button is enabled in idle state', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'idle')
            ->assertDontSee('cursor-not-allowed');
    });

    it('passes audioUrl through when server audio is present (Tier 2 path)', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(RecordingButton::class)
            ->call('onAiResponseReady', [
                'recording_id'  => 1,
                'response_text' => 'Wah yaar!',
                'audio_url'     => 'https://example.com/responses/1/response.mp3',
            ])
            ->assertSet('uiState', 'playing')
            ->assertDispatched('play-ai-response', function (string $event, array $params) {
                return $params['audioUrl'] !== null; // Tier 2 JS path triggered
            });
    });
});
```

---

## 6. Upgrade Path — OpenAI TTS via `laravel/ai` (Tier 2)

> **Trigger:** Implement when voice quality is a measurable user complaint or at growth stage.  
> **Effort:** ~1 day (backend only — no frontend changes required).

### What changes vs. what stays the same

| | Tier 1 (MVP) | Tier 2 (After upgrade) |
|-|-------------|----------------------|
| Frontend JS | `speakWithWebSpeech(text)` | `playAudioFile(audioUrl, text)` |
| `audioUrl` in payload | `null` | Populated URL |
| Backend job | None | `GenerateTtsAudio` on `tts` queue |
| `laravel/ai` | Unused | `Ai::speech()->using(Lab::OpenAi, 'tts-1')` |
| Cost | Free | ~$0.015 / 1K chars |
| **Frontend code change** | — | **None required** |

### Step U1 — Configure OpenAI Key

```dotenv
# .env
OPENAI_API_KEY=your-openai-api-key
```

`laravel/ai` reads `OPENAI_API_KEY` automatically. No additional config changes needed.

### Step U2 — Create `TextToSpeechService`

```php
<?php
// app/Services/TTS/TextToSpeechService.php

declare(strict_types=1);

namespace App\Services\TTS;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Facades\Ai;

final class TextToSpeechService
{
    /** Hard cap: keeps voice responses concise (≈ 30s of speech). */
    private const MAX_CHARS = 500;

    /**
     * Synthesise speech via laravel/ai → OpenAI TTS.
     * Returns the storage-relative path of the saved MP3.
     */
    public function synthesize(string $text, int $userId): string
    {
        $text = $this->prepareText($text);

        // laravel/ai is the only way we call any AI/TTS service.
        // 'nova' = warm, conversational. Switch to 'shimmer' for a softer tone.
        $audioContent = Ai::speech()
            ->using(Lab::OpenAi, 'tts-1')
            ->voice('nova')
            ->generate($text);

        $filename    = 'response_' . $userId . '_' . time() . '.mp3';
        $storagePath = "responses/{$userId}/{$filename}";

        Storage::disk('public')->put($storagePath, $audioContent);

        Log::info('[TTS] Audio generated via laravel/ai (OpenAI tts-1)', [
            'user_id' => $userId,
            'path'    => $storagePath,
            'chars'   => mb_strlen($text),
        ]);

        return $storagePath;
    }

    private function prepareText(string $text): string
    {
        // Strip any Markdown artefacts that leaked through
        $text = preg_replace('/\*+([^*]+)\*+/', '$1', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) <= self::MAX_CHARS) {
            return $text;
        }

        $truncated  = mb_substr($text, 0, self::MAX_CHARS);
        $lastPeriod = mb_strrpos($truncated, '.');

        return ($lastPeriod !== false && $lastPeriod > self::MAX_CHARS * 0.6)
            ? mb_substr($truncated, 0, $lastPeriod + 1)
            : $truncated . '…';
    }
}
```

### Step U3 — Create `GenerateTtsAudio` Job

```php
<?php
// app/Jobs/GenerateTtsAudio.php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\AiAudioReady;
use App\Models\Recording;
use App\Services\TTS\TextToSpeechService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class GenerateTtsAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue   = 'tts';
    public int    $tries   = 2;
    public int    $timeout = 15;

    public function __construct(
        public readonly Recording $recording,
    ) {}

    public function handle(TextToSpeechService $tts): void
    {
        if (blank($this->recording->ai_response_text)) {
            Log::warning('[TTS] Skipped — no response text', [
                'recording_id' => $this->recording->id,
            ]);
            $this->broadcastReady(audioPath: null);
            return;
        }

        try {
            $audioPath = $tts->synthesize(
                $this->recording->ai_response_text,
                $this->recording->user_id,
            );

            $this->recording->update(['ai_response_audio_path' => $audioPath]);
            $this->broadcastReady($audioPath);

        } catch (\Throwable $e) {
            Log::error('[TTS] laravel/ai synthesis failed — broadcasting text-only fallback', [
                'recording_id' => $this->recording->id,
                'error'        => $e->getMessage(),
            ]);
            // No audio path → client falls back to Web Speech API automatically
            $this->broadcastReady(audioPath: null);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[TTS] Job permanently failed', [
            'recording_id' => $this->recording->id,
            'error'        => $e->getMessage(),
        ]);
        $this->broadcastReady(audioPath: null); // Always unblock the UI
    }

    private function broadcastReady(?string $audioPath): void
    {
        $recording = $audioPath ? $this->recording->fresh() : $this->recording;
        AiAudioReady::dispatch($recording);
    }
}
```

### Step U4 — Create `AiAudioReady` Broadcast Event

```php
<?php
// app/Events/AiAudioReady.php

declare(strict_types=1);

namespace App\Events;

use App\Models\Recording;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class AiAudioReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Recording $recording,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->recording->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'ai.audio.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'recording_id' => $this->recording->id,
            'text'         => $this->recording->ai_response_text,
            'audio_url'    => $this->recording->ai_response_audio_path
                ? Storage::disk('public')->url($this->recording->ai_response_audio_path)
                : null,
        ];
    }
}
```

### Step U5 — Update Livewire to Listen for `AiAudioReady`

When Tier 2 is active, `ProcessRecording` dispatches `GenerateTtsAudio` which broadcasts `AiAudioReady` (instead of `AiResponseReady` being the final signal). Update the listener in `RecordingButton.php`:

```php
// Replace the Tier 1 listener with this for Tier 2:
#[On('echo-private:user.{auth.id},ai.audio.ready')]
public function onAiAudioReady(array $event): void
{
    $this->uiState       = 'playing';
    $this->statusMessage = 'Dost is speaking...';

    $this->dispatch('play-ai-response', [
        'text'     => $event['text']      ?? '',
        'audioUrl' => $event['audio_url'] ?? null, // non-null = MP3 plays; null = Web Speech fallback
    ]);
}
```

### Step U6 — Add TTS Queue Worker

`docker/8.3/supervisord.conf`:

```ini
[program:laravel-queue-tts]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=tts --sleep=3 --tries=2 --timeout=15
autostart=true
autorestart=true
user=sail
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-tts.log
```

### Step U7 — TTS File Cleanup

No changes to DATA-01 needed.  
`CleanupAudio` already handles `ai_response_audio_path` — it nulls the column and deletes the MP3 file after the user's retention period.

### Tier 2 Upgrade Checklist

- [ ] `OPENAI_API_KEY` added to `.env`
- [ ] `TextToSpeechService::synthesize()` returns valid storage path
- [ ] `GenerateTtsAudio` job dispatched from `ProcessRecording` (after `AiResponseReady`)
- [ ] `AiAudioReady` event broadcast with `audio_url` populated
- [ ] Livewire `onAiAudioReady` listener active (replaced `onAiResponseReady`)
- [ ] `<audio>` element plays MP3 on device
- [ ] Web Speech API fallback triggers when `audio_url` is null
- [ ] TTS queue worker running on `tts` queue in supervisord
- [ ] DATA-01 cleanup handles `responses/{user_id}/` MP3 files
- [ ] `composer test` passes all TTS tests

---

## 7. Verification Checklist (Tier 1 MVP)

- [ ] `AiResponseReady` broadcast received by `RecordingButton` component
- [ ] `uiState` transitions to `'playing'` on receipt
- [ ] `play-ai-response` dispatched to JS with `audioUrl: null`
- [ ] `window.speechSynthesis.speak()` called with `lang: 'en-IN'`
- [ ] Indian English (`en-IN`) voice selected when available on device
- [ ] Mic button in disabled / `cursor-not-allowed` state during playback
- [ ] `onPlaybackFinished()` called after `utterance.onend`
- [ ] `uiState` returns to `'idle'` after playback
- [ ] All error paths call `onPlaybackFinished()` — mic never hangs
- [ ] Smoke-tested on physical Android device (API 29+)
- [ ] `composer test` passes all VOICE-03 Livewire tests

---

## 8. Acceptance Criteria

1. User hears Dost's voice within **3 seconds** of the AI response being ready.
2. Voice plays via Web Speech API in `en-IN` locale at zero server cost.
3. Mic button is **disabled** for the entire duration of TTS playback.
4. Mic button **re-enables** immediately after `utterance.onend` (or on any error path).
5. If Web Speech API is unavailable or errors, mic unblocks gracefully — no hang, no crash.
6. Complete voice loop works end-to-end: **hold → record → release → AI thinks → Dost speaks → idle**.
7. When the Tier 2 OpenAI TTS upgrade is applied (Section 6), **zero frontend code changes** are required.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Web Speech API unavailable in NativePHP WebView | `onPlaybackFinished()` is called in every error/null path — mic always unblocks |
| `en-IN` voice not installed on device | Falls back to device default TTS voice — speech still plays, just different accent |
| `getVoices()` returns empty array on first call | `onvoiceschanged` listener handles async voice loading (Android WebView quirk) |
| Long AI response slow to play | VOICE-02 prompt constrains Gemini to 2–3 sentences; `TextToSpeechService` caps at 500 chars |
| OpenAI TTS key missing during Tier 2 rollout | `GenerateTtsAudio` catches the error → broadcasts `audio_url: null` → client falls back to Web Speech automatically |
| MP3 files accumulate on storage | DATA-01 `audio:cleanup` nulls `ai_response_audio_path` and deletes the file after retention period |
| `laravel/ai` TTS API surface changes in future | All TTS calls are isolated to `TextToSpeechService` — one class to update |
| Both Tier 1 and Tier 2 fail simultaneously | Final `$wire.call('onPlaybackFinished')` in all catch/error branches ensures mic always unblocks |
