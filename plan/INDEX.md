# Dost — Detailed Execution Plan Index

**Project:** Dost — AI Voice Tutor for Indian Learners  
**Stack:** Laravel 13 · NativePHP Mobile · Livewire 3 · Gemini 2.5 Flash · PostgreSQL · Reverb  
**Target Platform:** Android (NativePHP Air v3)

---

## Plan Directory Structure

```
plan/
├── INDEX.md                          ← This file
├── QUESTIONS.md                      ← Open questions / blockers
├── INF-01-docker-setup.md            ← Phase 1
├── INF-02-code-quality.md            ← Phase 1
├── INF-03-mcp-docs-sync.md           ← Phase 1
├── MOB-01-nativephp-android.md       ← Phase 2
├── AUTH-01-mobile-auth.md            ← Phase 2
├── RND-01-gemini-audio-spike.md      ← Phase 3
├── VOICE-01-hold-to-speak.md         ← Phase 3
├── VOICE-02-tutor-agent.md           ← Phase 3
├── VOICE-03-tts-playback.md          ← Phase 3
├── DATA-01-pruning-privacy.md        ← Phase 4
├── UI-02-progress-dashboard.md       ← Phase 4
├── DEMO-01-ai-sdk-showcase.md       ← Standalone (presentation demo)
└── DEMO-02-blog-studio.md           ← Standalone (unified demo — all features in one page)
```

---

## Completed Tickets

| Ticket | Title | Completed | Notes |
|--------|-------|-----------|-------|
| INF-01 | Dockerized Laravel 13.x Setup | March 2026 | Docker + PostgreSQL + Reverb + PHP 8.4 |
| INF-02 | Code Quality Toolchain | March 2026 | Pint + Larastan L5 + PHPMD + Pest; `composer check` wired up |
| INF-03 | MCP Documentation Sync | March 21, 2026 | context7 & docs/context/ dropped — Laravel Boost `search-docs` + instruction files (CLAUDE.md, AGENTS.md, GEMINI.md, .github/copilot-instructions.md) cover the need |
| MOB-01 | NativePHP Android Bootstrapping | March 21, 2026 | NativePHP + Microphone + Device plugins installed; `native:jump` working on physical device via WiFi; rsync added to Docker image; port 3000 exposed |
| AUTH-01 | Mobile-Optimized Authentication | March 21, 2026 | Breeze + Livewire 4 + Volt installed; Tailwind v4 conflict resolved; dark warm mobile-first auth views; `audio_retention_days` + `RetentionDays` enum; 27 tests passing; `composer check` fully green |
 RND-01  Gemini Audio Compatibility Spike  March 23, 2026  Verified via `./bin/artisan spike:audio`: filesystem-detected `audio/x-m4a`, `audio/mp4`, `audio/aac`, and `audio/m4a` all accepted by Gemini 2.5 Flash; no FFmpeg needed; latency 3.5s–4.0s; `laravel/ai` installed 
 VOICE-01  Hold-to-Speak Recording Logic  March 21, 2026  `recordings` table + `Recording` model + `RecordingStatus` enum; `RecordingButton` Livewire component; `RecordingFinished` event; `ProcessRecording` queued listener stub; 9 Pest tests passing; `composer check` green
 VOICE-02  TutorAgent AI Integration  March 22, 2026  `TutorAgent` + `TutorProcessor` + `TutorResult` + `AiResponseReady` event + `GenerateTtsAudio` stub; `EventServiceProvider` wires `RecordingFinished → ProcessRecording`; supervisord queue workers added (`default` ×2, `ai` ×2, scheduler ×1); 4 Pest tests; `composer check` green
 VOICE-03  AI Response & TTS Playback  March 22, 2026  Tier 1 MVP: `onAiResponseReady(array $event)` uses broadcast payload directly (no DB query); robust Web Speech API with `onerror`, Indian voice selection, `onvoiceschanged` async guard; Tier 2 `<audio>` element pre-wired; 7 Pest tests; `composer check` green
 DATA-01  Pruning & Privacy Policy  March 22, 2026  `recordings.path` made nullable (migration); `recordings_analytics_index` added; `CleanupAudio` command with `--dry-run` + `--user` options; scheduled daily at 02:00; `AudioRetention` settings Livewire component with logout; mobile bottom nav replaces Breeze top nav; `User::recordings()` relationship; `duration_seconds` estimated from file size; 9 Pest tests; `composer check` green

---

## Ticket Dependency Graph

```
INF-01 (Docker)
    │
    ├──▶ INF-02 (Code Quality)
    │        │
    │        └──▶ INF-03 (MCP Docs)
    │
    └──▶ MOB-01 (NativePHP Android)
             │
             └──▶ AUTH-01 (Mobile Auth)
                      │
                      ▼
              RND-01 (Audio Spike) ◀── Must run before VOICE tickets
                      │
                      ▼
              VOICE-01 (Hold-to-Speak)
                      │
                      ▼
              VOICE-02 (TutorAgent)
                      │
                      ▼
              VOICE-03 (TTS Playback)
                      │
          ┌───────────┴──────────────┐
          ▼                          ▼
   DATA-01 (Pruning)         UI-02 (Progress)
```

---

## Phase Overview

### Phase 1: Infrastructure (7 tickets → 9h total)

| Ticket | Title                         | Est. | Complexity | Status |
|--------|-------------------------------|------|-----------|--------|
| INF-01 | Dockerized Laravel 13.x Setup | 4h | 2 | ✅ Done |
| INF-02 | Code Quality Toolchain        | 2h | 1 | ✅ Done |
| INF-03 | MCP Documentation Sync        | 3h | 3 | ✅ Done |

**Phase 1 Goal:** A working Docker environment where all developers can run `docker compose up -d` and get a fully configured Laravel 13 app with Postgres, Reverb, and all quality tools wired up.

---

### Phase 2: Mobile & Auth (9h total)

| Ticket | Title | Est. | Complexity | Status |
|--------|-------|------|-----------|--------|
| MOB-01 | NativePHP Android Bootstrapping | 6h | 4 | ✅ Done |
| AUTH-01 | Mobile-Optimized Auth | 3h | 2 | ✅ Done |

**Phase 2 Goal:** An Android APK that loads the Laravel app, displays a dark, warm, mobile-first login/register screen, and successfully authenticates users.

---

### Phase 3: Voice Core (20h total)

| Ticket | Title | Est. | Complexity | Status |
|--------|-------|------|-----------|--------|
| RND-01 | Gemini Audio Compatibility Spike | 5h | 5 | ✅ Done |
 VOICE-01  Hold-to-Speak Recording Logic  5h  4  ✅ Done 
| VOICE-02 | TutorAgent AI Integration | 6h | 4 | ✅ Done |
| VOICE-03 | AI Response & TTS Playback | 4h | 3 | ✅ Done |

**Phase 3 Goal:** A working voice loop: User holds → records → releases → AI processes → voice plays back. The "soul" of the app.

---

### Phase 4: Analytics & Maintenance (7h total)

| Ticket | Title | Est. | Complexity |
|--------|-------|------|-----------|
| DATA-01 | Pruning & Privacy Policy | 2h | 1 | ✅ Done |
| UI-02 | Progress Dashboard | 5h | 3 |

**Phase 4 Goal:** Users can see their speaking progress, feel motivated, and trust their data is handled responsibly.

---

### Standalone: Presentation Demos

| Ticket | Title | Est. | Complexity | Status |
|--------|-------|------|-----------|--------|
| DEMO-01 | Laravel AI SDK Showcase (7 pages) | 4–6h | 3 | ✅ Implementation complete |
| DEMO-02 | Blog Studio (unified single-page demo) | 3–4h | 3 | 🔲 Not started |

**Goal:** 7 public Livewire pages (`/demo/*`) — one per PPT slide — showcasing `laravel/ai` text, image, audio, embeddings, agents+tools, and failover. Independent from Dost features. Requires `GEMINI_API_KEY` + `OPENAI_API_KEY`.

---

## Total Estimate: ~36h of development (+ ~5h demo)

---

## Key Technical Decisions (Confirmed)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Laravel AI SDK | `laravel/ai` v0.3.2 (wraps `prism-php/prism` v0.99.x) | Official Laravel 13 SDK; multi-provider; confirmed in Q1 |
| LLM Provider | Gemini 2.5 Flash via `Lab::Gemini` | Cost/latency balance; Google AI Studio free tier for MVP |
| TTS — MVP | Web Speech API (`en-IN` locale) | Zero cost; works in NativePHP WebView; upgradeable |
| TTS — Upgrade | OpenAI TTS (`tts-1` / `nova`) via `laravel/ai` | Any AI service goes through `laravel/ai` exclusively |
| Audio Format | `.m4a` / `audio/mp4` (verify in R&D-01) | Native NativePHP microphone output |
| Database — Dev Docker | PostgreSQL 16 | Richer dev tooling |
| Database — Android Build | SQLite | NativePHP Mobile recommended (on-device, no server process) |
| Queue Driver — Dev | `database` (PostgreSQL or SQLite) | Simple setup for MVP |
| Queue Driver — Prod server | Valkey 8 (open-source Redis fork, BSD-3-Clause) | Drop-in Redis replacement; no license concerns |
| PHPStan Level | **5** | As specified in blueprint ("Level 5+"); confirmed in Q12 |
| Pint Preset | `laravel` | PSR-12 superset; confirmed in Q11 |
| Android Min SDK | API 29 (Android 10+) | NativePHP WebView compatible; ~90% market coverage |
| Conversation Scope | Daily (new per day) | Simplest for MVP; confirmed in Q7 |
| Recording Cleanup | Keep DB row, null audio paths after `expires_at` | Preserves stats for UI-02; confirmed Option C in Q4 |
| WebSockets | Laravel Reverb (self-hosted MVP) | Q15 resolved; Pusher Sandbox viable for small beta |
| Laravel Version | **13** (confirmed) | `nativephp/mobile` v3 updated tag (March 2026) — now supports `illuminate/contracts ^13.x`; installed: **13.1.1** (Q3b) |

---

## Database Schema Summary

```
users
  └─ id, name, email, password, audio_retention_days (1/2/7), ...

recordings
  └─ id, user_id, path (nullable after cleanup), mime_type,
     duration_seconds, file_size_bytes,
     status (pending/processing/completed/failed),
     transcript, ai_response_text, ai_response_audio_path (nullable after cleanup),
     expires_at, timestamps

── Managed by laravel/ai (agent_conversations + agent_conversation_messages) ──
agent_conversations
  └─ id, user_id, agent (class name), title, timestamps

agent_conversation_messages
  └─ id, conversation_id, role (user/assistant), content, timestamps
```

> **Dev Docker:** PostgreSQL 16  
> **Android Build:** SQLite (on-device file, managed by NativePHP — see MOB-01 Step 6)

---

## Queue Architecture

```
Queues:
  default  — General jobs
  ai       — TutorAgent / AI processing (timeout: 30s)
  tts      — Text-to-Speech generation (timeout: 15s) ← Tier 2 upgrade only

Queue Driver:
  Dev Docker  — database (PostgreSQL-backed)
  Android     — database (SQLite-backed, no external server)
  Production  — Valkey 8 (open-source Redis fork, drop-in compatible)

Workers (supervisord / Docker):
  2x default
  2x ai
  2x tts       ← only needed when upgrading to OpenAI TTS (VOICE-03 Tier 2)
  1x scheduler (cron via schedule:work)
```

---

## Broadcast Events Flow

```
RecordingFinished   → ProcessRecording listener → TutorAgent
AiResponseReady     → GenerateTtsAudio job      → TextToSpeechService
AiAudioReady        → Livewire (via Reverb)     → UI plays audio
```

---

## Pending Questions

See **[QUESTIONS.md](./QUESTIONS.md)** for all open questions.  
**There are no current blockers for Phase 2.** The previously blocking questions are resolved:
1. **Q3b** — Laravel 12 vs 13? ✅ **Laravel 13 confirmed** — NativePHP Mobile 3.x released updated tag supporting `illuminate/contracts ^13.x` (March 2026)
2. **Q16** — SQLite (device-local) vs PostgreSQL (remote server) architecture? ✅ **Resolved in favor of device-local SQLite for Android**

All other questions are resolved or explicitly deferred.

---

## Notes for Future Phases (Post-MVP)

- **Valkey** queue driver + Laravel Horizon for queue monitoring (replaces `database` driver)
- **VOICE-03 Tier 2** — OpenAI TTS via `laravel/ai` when voice quality needs improving (1-day upgrade)
- **Gemini Live API** for real-time streaming conversation (eliminates walkie-talkie delay)
- **Conversation persistence** — switch from daily-reset to per-level or per-week scope
- **Push Notifications** for streak reminders via `nativephp/mobile`
- **Conversation export** (user downloads their recordings as practice material)
- **Multiple topics/modes** (Job Interview practice, Daily Conversation, etc.)
- **Onboarding flow** (UX-01 ticket — deferred, see Q10)
