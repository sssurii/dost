# ADR-001: Audio Format Strategy

**Date:** March 21, 2026
**Status:** Decided
**Decision Makers:** Dost Engineering

---

## Context

NativePHP Mobile's `Microphone` plugin records audio in `.m4a` format (AAC-LC codec inside an MPEG-4 container). Before building the voice pipeline (VOICE-01 → VOICE-02), we needed to confirm that Gemini 2.5 Flash accepts `.m4a` audio directly via the `laravel/ai` SDK — or whether every recording would need server-side conversion to `.wav` / `.ogg` first.

## Spike Results (RND-01 — March 21, 2026)

Test file: 5-second synthetic AAC tone, 79 KB, via `Files\Audio::fromStorage()` + `laravel/ai` `v0.3.2`, Gemini `gemini-2.5-flash`.

| MIME type      | Result  | Latency |
|----------------|---------|---------|
| `audio/mp4`    | ✅ Pass | 3605ms  |
| `audio/aac`    | ✅ Pass | 5057ms  |
| `audio/m4a`    | ✅ Pass | 3241ms  |

All three MIME types were accepted. Gemini successfully described the audio content in all cases.

## Decision

**Use `audio/mp4` as the MIME type for all `.m4a` recordings.** It is the correct IANA-registered MIME type for the MPEG-4 audio container and produced the lowest consistent latency.

No server-side audio conversion is required. `spatie/laravel-ffmpeg` will **not** be added to the stack.

## Implementation Notes for VOICE-02

- Use `Files\Audio::fromStorage($path, disk: 'local')` — the SDK auto-detects MIME from extension; pass `'audio/mp4'` explicitly if detection fails.
- Storage path pattern: `recordings/{user_id}/{filename}.m4a` on the `local` disk.
- Latency budget: the Gemini API call alone takes ~3–5 seconds for short clips. The full end-to-end target (record → AI response ready) is **≤ 6 seconds** for a 5–30 second clip.

## Consequences

- ✅ No FFmpeg dependency — simpler Dockerfile, no conversion latency.
- ✅ No quality loss from re-encoding.
- ✅ `laravel/ai` `Files\Audio::fromStorage()` is the correct attachment API for VOICE-02.
- ⚠️ API latency (~3–5s) is close to the 3s target. Real speech audio may be faster than a synthetic tone; monitor in production.

