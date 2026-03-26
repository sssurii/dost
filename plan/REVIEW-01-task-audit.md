# Task Review Audit

**Date:** March 23, 2026  
**Scope:** Implemented tickets listed as completed in `plan/INDEX.md`  
**Review mode:** Senior Laravel / architecture review  

---

## Priority Order

### P0 — Fix Immediately

#### 1. Dead TTS queue path in VOICE-02 / VOICE-03

**Area:** `VOICE-02`, `VOICE-03`

**What is wrong**

- `ProcessRecording` always dispatches `GenerateTtsAudio`
- `GenerateTtsAudio` routes itself to the `tts` queue
- the job has no implementation
- Docker has no `tts` worker service

**Why this priority is urgent**

This creates dead jobs on every successful AI response. It is not just incomplete code sitting idle; it actively produces queued work that will never be processed.

**Consequences**

- queue buildup in real usage
- misleading system behavior: pipeline appears complete but is not
- operational noise and harder debugging later
- possible user-facing inconsistency if future code assumes the TTS job ran

**Recommended solution**

Choose one and make it explicit:

1. If Tier 2 TTS is not part of the current MVP, stop dispatching `GenerateTtsAudio` for now.
2. If Tier 2 should exist now, implement the job and add a `tts` worker to Docker.

**Relevant files**

- [app/Listeners/ProcessRecording.php](/home/fnl/dev/dost/app/Listeners/ProcessRecording.php)
- [app/Jobs/GenerateTtsAudio.php](/home/fnl/dev/dost/app/Jobs/GenerateTtsAudio.php)
- [docker-compose.yml](/home/fnl/dev/dost/docker-compose.yml)

---

#### 2. RND-01 spike command does not validate the MIME conclusion it reports

**Area:** `RND-01`

**What is wrong**

The command loops over three MIME labels, but never actually varies the MIME sent to Gemini. The verdict claims all three MIME variants were tested, but the implementation always attaches the same file the same way.

**Why this priority is urgent**

This is an architectural decision input. If the spike result is invalid, later ticket decisions that depend on it may be built on false evidence.

**Consequences**

- wrong technical decision about accepted MIME types
- false confidence that no conversion step is needed
- fragile future debugging if Gemini behavior differs from the claimed test

**Recommended solution**

Update the spike so each test run actually sends the intended MIME variant, or reduce the command’s claims to only what is truly tested. Then rerun the spike and update the plan notes to match the real result.

**Relevant files**

- [app/Console/Commands/SpikeAudioCommand.php](/home/fnl/dev/dost/app/Console/Commands/SpikeAudioCommand.php)

---

### P1 — High Priority

#### 3. Audio retention update logic changes expiry relative to “now”, not recording age

**Area:** `DATA-01`

**What is wrong**

When the user changes retention days, the code rewrites active recordings to `now()->addDays(...)` instead of recalculating expiry from the recording’s original time.

**Why this priority is high**

This breaks the privacy policy semantics. Retention should mean “keep for N days after recording”, not “keep until N days from whenever the setting was changed”.

**Consequences**

- overly long retention for old recordings
- inconsistent cleanup timing
- privacy expectation mismatch
- policy becomes difficult to explain to users

**Recommended solution**

Recompute `expires_at` from each recording’s creation time, or define and document a different business rule if that is truly intended. The safer default is:

- `expires_at = created_at + retention_days`

for eligible recordings.

**Relevant files**

- [app/Livewire/Settings/AudioRetention.php](/home/fnl/dev/dost/app/Livewire/Settings/AudioRetention.php)

---

#### 4. Recording model accessors no longer match nullable schema

**Area:** `DATA-01`

**What is wrong**

`recordings.path` is now nullable after cleanup, but `getFullPathAttribute()` and `getPublicUrlAttribute()` still assume `path` is always a string.

**Why this priority is high**

This is a latent production bug. The schema and the model contract disagree, which makes future consumers likely to crash when touching cleaned-up recordings.

**Consequences**

- runtime failures on cleaned-up rows
- hidden bugs when analytics, exports, or admin tooling later reuse these accessors
- model API becomes misleading

**Recommended solution**

Make the accessors nullable and guard against null paths, or remove them if they are not safe to expose after cleanup.

**Relevant files**

- [app/Models/Recording.php](/home/fnl/dev/dost/app/Models/Recording.php)
- [database/migrations/2026_03_22_171552_make_recordings_path_nullable_add_analytics_index.php](/home/fnl/dev/dost/database/migrations/2026_03_22_171552_make_recordings_path_nullable_add_analytics_index.php)

---

#### 5. AI response playback is scoped only to the user channel, not the active recording

**Area:** `VOICE-03`, `UI-02`

**What is wrong**

`RecordingButton` reacts to any `recording.completed` broadcast for that user. It does not verify that the event belongs to `currentRecordingId`.

**Why this priority is high**

This can create incorrect UI state and wrong playback in realistic multi-tab or repeated-use scenarios.

**Consequences**

- wrong response may play
- mic may be locked unexpectedly
- stale or parallel events can hijack the component state
- harder real-time debugging later

**Recommended solution**

Gate the handler by `recording_id === currentRecordingId`, or use a recording-specific event/channel strategy if that better fits the architecture.

**Relevant files**

- [app/Livewire/Voice/RecordingButton.php](/home/fnl/dev/dost/app/Livewire/Voice/RecordingButton.php)
- [app/Events/AiResponseReady.php](/home/fnl/dev/dost/app/Events/AiResponseReady.php)

---

### P2 — Medium Priority

#### 6. Browser fallback for recording is misleading and effectively broken

**Area:** `VOICE-01`

**What is wrong**

When NativePHP is unavailable, the frontend sends `/tmp/fake-recording.m4a` to Livewire. The backend then tries to read that path from the PHP container filesystem, where the file does not exist.

**Why this priority is medium**

It does not break the intended NativePHP mobile path, but it makes browser-side development and debugging misleading.

**Consequences**

- confusing developer experience
- false impression that browser fallback exists
- predictable error state outside NativePHP

**Recommended solution**

Either:

1. remove the fake-browser fallback and fail explicitly with a clear message, or
2. implement a real browser-safe fallback path for development

The first option is simpler and less misleading.

**Relevant files**

- [resources/views/livewire/voice/recording-button.blade.php](/home/fnl/dev/dost/resources/views/livewire/voice/recording-button.blade.php)
- [app/Livewire/Voice/RecordingButton.php](/home/fnl/dev/dost/app/Livewire/Voice/RecordingButton.php)

---

#### 7. `.env.example` does not match the intended Docker development stack

**Area:** `INF-01`

**What is wrong**

The example environment still defaults to SQLite, `log` broadcasting, and `local` filesystem, while the project’s documented Docker setup expects PostgreSQL, Reverb, and public storage usage.

**Why this priority is medium**

It does not break an already-configured local environment, but it slows onboarding and creates avoidable setup drift.

**Consequences**

- wrong defaults for new developers
- inconsistent environment setup
- more manual repair after copying `.env.example`

**Recommended solution**

Align `.env.example` with the actual intended Docker development defaults, or provide a clearly separated Docker-specific example file if the repo must support multiple bootstrap modes.

**Relevant files**

- [.env.example](/home/fnl/dev/dost/.env.example)

---

## Suggested Fix Order

1. Dead TTS queue path
2. Invalid RND-01 spike command
3. Retention semantics bug
4. Nullable recording path accessors
5. Playback event scoping
6. Browser fallback cleanup
7. `.env.example` alignment

---

## Review Notes

- These priorities reflect production risk, architectural correctness, and likelihood of causing misleading behavior or future defects.
- Style-only issues were intentionally excluded.
- The current quality suite may still pass while some of these issues exist, because several are architectural or behavioral gaps rather than directly failing assertions.
