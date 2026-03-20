# R&D-01: Gemini Audio Compatibility Spike

**Phase:** 3 — Voice Core  
**Complexity:** 5 | **Estimate:** 5h  
**Depends on:** INF-01, INF-02, INF-03  
**Blocks:** VOICE-01, VOICE-02 (must know audio pipeline before implementing)

---

## 1. Objective

**Definitively answer the question:** Can `Gemini 2.5 Flash` accept `.m4a` (AAC audio) files directly via the Laravel AI SDK, or do we need server-side conversion to `.wav`?

This is a **time-boxed R&D spike** (max 5h). The output is a clear YES/NO + a working code path, **not** production code.

---

## 2. Why This Matters

NativePHP Mobile's `Microphone` plugin records in `.m4a` (AAC container, AAC-LC codec). Sending audio to Gemini requires a supported MIME type. If `.m4a`/`audio/mp4` is not accepted, every recording must be converted server-side — adding latency, CPU cost, and the `spatie/laravel-ffmpeg` dependency.

---

## 3. Hypothesis Matrix

| Scenario | Probability | Latency Impact | Cost Impact |
|----------|------------|----------------|-------------|
| Gemini accepts `audio/mp4` (M4A) directly | High (80%) | None | None |
| Gemini accepts `audio/aac` MIME type | Medium (50%) | None | None |
| Must convert M4A → WAV (FFmpeg) | Low (20%) | +200-500ms | +CPU |
| Must convert M4A → OGG/Opus (smaller) | Low (10%) | +200ms | Smaller payload |

---

## 4. Step-by-Step Spike Process

### Step 1 — Install Laravel AI SDK

```bash
composer require laravel/ai

php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

Update `.env`:

```dotenv
GEMINI_API_KEY=your-api-key-from-aistudio.google.com
```


### Step 2 — Create the Spike Test Script

Create `scripts/audio-spike.php` (a throwaway CLI script):

```php
<?php
// scripts/audio-spike.php
// Run: php artisan tinker < scripts/audio-spike.php

require __DIR__ . '/../vendor/autoload.php';

use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files;

// Anonymous inline agent (no need for a full class for the spike)
use Laravel\Ai\Promptable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class SpikeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a helpful assistant that transcribes audio.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language'   => $schema->string()->required(),
            'transcript' => $schema->string()->required(),
        ];
    }
}

$testAudioPath = __DIR__ . '/../storage/app/audio-spike/test.m4a';

// Ensure test audio exists
if (! file_exists($testAudioPath)) {
    echo "ERROR: Test audio file not found at: {$testAudioPath}\n";
    echo "Record a 5-second test clip and place it there.\n";
    exit(1);
}

$fileSize = round(filesize($testAudioPath) / 1024, 2);
echo "Test file size: {$fileSize} KB\n";
echo "Attempting direct M4A submission via laravel/ai + Files\Audio...\n\n";

// ── Test 1: Files\Audio::fromPath() attachment (native laravel/ai approach) ─
echo "=== Test 1: Files\Audio::fromPath() with Gemini ===\n";
$start = microtime(true);
try {
    $response = (new SpikeAgent)->prompt(
        'What language is spoken in this audio? Transcribe the first sentence.',
        provider: Lab::Gemini,
        model: 'gemini-2.5-flash',
        attachments: [
            Files\Audio::fromPath($testAudioPath),
        ]
    );

    $latency = round((microtime(true) - $start) * 1000, 2);
    echo "✅ SUCCESS\n";
    echo "Language: " . $response['language'] . "\n";
    echo "Transcript: " . $response['transcript'] . "\n";
    echo "Latency: {$latency}ms\n\n";
} catch (\Throwable $e) {
    $latency = round((microtime(true) - $start) * 1000, 2);
    echo "❌ FAILED ({$latency}ms)\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ── Test 2: Files\Audio::fromStorage() via disk ─────────────────────────────
echo "=== Test 2: Files\Audio::fromStorage() via disk ===\n";
// Copy to storage first
\Illuminate\Support\Facades\Storage::disk('local')->put(
    'audio-spike/test.m4a',
    file_get_contents($testAudioPath)
);
$start = microtime(true);
try {
    $response = (new SpikeAgent)->prompt(
        'What language is spoken in this audio? Transcribe the first sentence.',
        provider: Lab::Gemini,
        model: 'gemini-2.5-flash',
        attachments: [
            Files\Audio::fromStorage('audio-spike/test.m4a', disk: 'local'),
        ]
    );

    $latency = round((microtime(true) - $start) * 1000, 2);
    echo "✅ SUCCESS\n";
    echo "Language: " . $response['language'] . "\n";
    echo "Latency: {$latency}ms\n\n";
} catch (\Throwable $e) {
    $latency = round((microtime(true) - $start) * 1000, 2);
    echo "❌ FAILED ({$latency}ms)\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}
```

### Step 3 — Generate a Test Audio File

```bash
# Option A: Use ffmpeg on host to generate a 5-second test M4A
ffmpeg -f lavfi -i "sine=frequency=440:duration=5" \
       -c:a aac -b:a 128k \
       storage/app/audio-spike/test.m4a

# Option B: Record manually on Android device via Jump app
# and pull via ADB:
adb pull /data/user/0/com.dost.app/files/recordings/test.m4a \
    storage/app/audio-spike/test.m4a

# Create the directory
mkdir -p storage/app/audio-spike
```

### Step 4 — Run the Spike

```bash
docker compose exec laravel.test php scripts/audio-spike.php
```

### Step 5 — Latency Measurement

```bash
# Add timing to the test — modify the script header:
$start = microtime(true);
// ... run the request ...
$latency = round((microtime(true) - $start) * 1000, 2);
echo "Latency: {$latency}ms\n";
```

Expected latency targets:
- Voice message processing: **< 3 seconds** end-to-end
- Gemini API call alone: **< 2 seconds** for 30s audio

### Step 6 — Conditional: FFmpeg Fallback Test

**Only if Steps 2-4 fail for all M4A MIME types:**

```bash
# Install spatie/laravel-ffmpeg
composer require spatie/laravel-ffmpeg

# Install ffmpeg in Docker container
docker compose exec laravel.test bash
apt-get install -y ffmpeg
```

```php
// Conversion script - scripts/audio-conversion-test.php
use Spatie\LaravelFFMpeg\Facades\FFMpeg;
use FFMpeg\Format\Audio\Wav;

$m4aPath = storage_path('app/audio-spike/test.m4a');
$wavPath = storage_path('app/audio-spike/test.wav');

$start = microtime(true);

FFMpeg::fromDisk('local')
    ->open('audio-spike/test.m4a')
    ->export()
    ->toDisk('local')
    ->inFormat(new Wav())
    ->save('audio-spike/test.wav');

$conversionTime = round((microtime(true) - $start) * 1000, 2);
echo "Conversion time: {$conversionTime}ms\n";
echo "WAV file size: " . round(filesize($wavPath) / 1024 / 1024, 2) . " MB\n";
```

Expected conversion time: **< 500ms** for a 30s clip.

---

## 5. Decision Tree & Outcomes

```
Run M4A spike
     │
     ▼
Does audio/mp4 work? ──YES──▶ Use audio/mp4 MIME type in VOICE-02
     │                         No FFmpeg needed. Document in INF-03.
     NO
     │
     ▼
Does audio/aac work? ──YES──▶ Use audio/aac MIME type in VOICE-02
     │
     NO
     │
     ▼
Does audio/m4a work? ──YES──▶ Use audio/m4a MIME type in VOICE-02
     │
     NO
     │
     ▼
Convert M4A → WAV via FFmpeg
Add to stack:
  - spatie/laravel-ffmpeg
  - ffmpeg in Dockerfile
  - ConvertAudioJob (queued)
Document conversion latency.
Update VOICE-02 plan.
```

---

## 6. Expected Deliverables

After the spike, document findings in `docs/adr/ADR-001-audio-format.md`:

```markdown
# ADR-001: Audio Format Strategy

**Date:** [date]
**Status:** Decided
**Decision Makers:** [engineer name]

## Context
NativePHP records .m4a audio. Gemini 2.5 Flash requires a supported audio format.

## Decision
[FILL IN: Direct M4A / Convert to WAV / Convert to OGG]

## MIME Type to Use
[FILL IN: audio/mp4 / audio/aac / audio/wav]

## Latency Impact
- API call with [format]: [Xms]
- Conversion overhead: [N/A or Xms]

## Consequences
[Fill in implications for VOICE-02 implementation]
```

---

## 7. Verification Checklist

- [ ] Test `.m4a` file created successfully
- [ ] `laravel/ai` SDK installed and configured with Gemini API key
- [ ] `Files\Audio::fromPath()` attachment test returns a valid structured response
- [ ] Latency measured and documented
- [ ] `docs/adr/ADR-001-audio-format.md` written
- [ ] VOICE-02 plan confirmed compatible with `laravel/ai` attachment API
- [ ] If FFmpeg needed: `spatie/laravel-ffmpeg` added and tested

---

## 8. Acceptance Criteria

1. A definitive audio format strategy is documented in `ADR-001`.
2. A working code snippet (from the spike) is preserved as a reference in `docs/context/`.
3. Latency for Gemini API call is confirmed < 3 seconds for a 30-second audio clip.
4. If conversion is needed: conversion time confirmed < 500ms, FFmpeg in Dockerfile.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Gemini API key unavailable | Set up Google AI Studio account, use free tier |
| `laravel/ai` `Files\Audio` class not yet shipped | Check GitHub repo; fall back to `Files\Document` with audio mime, or raw Guzzle |
| M4A too large for inline base64 | Use `laravel/ai` Files API (`Files\Audio::fromPath()->put()`) for files > 5MB |
| FFmpeg conversion creates quality loss | Use WAV (lossless) as intermediate; don't re-encode M4A→AAC |
| Gemini SDK doesn't support audio attachments | Fall back to `google/generative-ai-php` package directly (last resort) |

---

## 10. Alternative SDK: Direct HTTP Fallback

If Prism doesn't support audio, use direct Gemini REST API:

```bash
composer require google/generative-ai-php
```

```php
use Gemini\Client;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\Part;
use Gemini\Enums\MimeType;

$client = Gemini::client(config('services.gemini.api_key'));

$response = $client
    ->geminiFlash()
    ->generateContent([
        Content::build()
            ->withParts([
                Part::fromBlob(
                    new Blob(
                        mimeType: MimeType::AUDIO_MP4,
                        data: base64_encode(file_get_contents($audioPath)),
                    )
                ),
                Part::fromText('Respond to this audio message as a warm English tutor.'),
            ]),
    ]);
```

