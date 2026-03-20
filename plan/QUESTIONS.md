# Open Questions & Clarifications Needed

**Created by:** Senior Engineering Review  
**Last updated:** March 2026 (Session 2 — all user answers incorporated)

---

## ✅ Resolved Questions

---

### Q1 — Laravel AI SDK: Which package? ✅ RESOLVED

**Answer:** Use **`laravel/ai`** — the official Laravel AI SDK.

| Detail | Value |
|--------|-------|
| Composer package | `laravel/ai` (latest stable: v0.3.2) |
| Under the hood | Wraps `prism-php/prism ^0.99.0` |
| Laravel compatibility | `illuminate/contracts ^12.0\|^13.0` ✅ |
| Namespace | `Laravel\Ai\...` |
| Provider enum | `Laravel\Ai\Enums\Lab` (e.g. `Lab::Gemini`, `Lab::OpenAi`) |
| Agent scaffold | `php artisan make:agent TutorAgent` |
| Traits | `Promptable`, `RemembersConversations` |
| Structured output | `HasStructuredOutput` + `schema(JsonSchema $schema): array` |
| Audio attachments | `Files\Audio::fromPath($path)` / `Files\Audio::fromStorage($path, disk: 'local')` |
| Install | `composer require laravel/ai` then publish + migrate |
| Tables created | `agent_conversations`, `agent_conversation_messages` |

> **Note:** `laravel/ai` v0.3.2 uses `prism-php/prism` v0.99.x internally. The VOICE-02 plan had incorrectly used `echolabsdev/prism` (old namespace). All plan files now use the `Laravel\Ai\...` API surface only.

**All plan files (VOICE-02, VOICE-03, RND-01, INF-03) have been updated accordingly.**

---

### Q2 — TTS Provider for Voice Response ✅ RESOLVED

**Answer:** **Web Speech API** (free, client-side) as **primary for MVP**. OpenAI TTS as a future upgrade only.

| Option | Decision |
|--------|----------|
| Web Speech API (browser, `en-IN` locale) | ✅ **MVP primary** — zero cost, works in NativePHP WebView |
| OpenAI TTS (`tts-1`, voice: `nova`) | 🔜 Upgrade path when voice quality needs improving |
| Google Cloud TTS | ❌ Not natively supported by `laravel/ai` |
| ElevenLabs | ❌ Too expensive for idea-validation stage |

**Rationale:** We're testing the idea. Voice quality can be improved later. If users need better voice quality, upgrade to OpenAI TTS (natively supported by `laravel/ai`) after validating the concept.

**VOICE-03 plan updated** — Web Speech API is primary; OpenAI TTS is a "future upgrade" note only.

---

### Q3 — NativePHP Mobile v3 Official Package Name ✅ RESOLVED (with ⚠️ warning)

**Confirmed:**
- **Composer package:** `nativephp/mobile` (https://packagist.org/packages/nativephp/mobile)
- **GitHub repo:** `https://github.com/NativePHP/mobile-air` — "Air" is the v3 internal/GitHub codename ✅
- **Latest stable:** `3.0.4`
- **PHP requirement:** `^8.3`

**⚠️ Note:** `nativephp/mobile` v3.0.4 declares `illuminate/contracts ^10.0|^11.0|^12.0`. Laravel 13 is not officially supported.

> **Resolution:** Laravel 12 is confirmed (Q3b). All compatibility risk is eliminated. `laravel/ai` v0.3.2 and `prism-php/prism` v0.99.x both support Laravel 12.

---

### Q4 — Audio Retention: DB Row Behaviour ✅ RESOLVED

**Answer:** **Option C** — Keep recording DB rows with text history; delete audio files; null out path columns.

**Why Option C:**
- `recordings.transcript` and `recordings.ai_response_text` survive → stats preserved
- `duration_seconds` survives → UI-02 progress dashboard continues to work
- File storage freed after retention period
- Simple query for active files: `WHERE path IS NOT NULL`

**DATA-01 plan updated** to implement Option C (null-path soft-cleanup).

---

### Q5 — Gemini API Key Source ✅ RESOLVED

**Answer:** Google AI Studio API key (`GEMINI_API_KEY`).  
Get at: https://aistudio.google.com → "Get API key" — free tier sufficient for MVP.

---

### Q7 — Conversation Persistence Model ✅ RESOLVED

**Answer:** **New conversation each day** (MVP).  
Future: may switch to persistent conversation per level or per week.

`TutorProcessor` in VOICE-02 implements daily-reset logic using `laravel/ai`'s `RemembersConversations` — checks `agent_conversations.created_at` against `today()`.

---

### Q8 — Android Minimum SDK Version ✅ RESOLVED

**Answer:** **Android 10+ (API 29)** minimum.  
`nativephp/mobile` v3.0.4 uses a WebView-based rendering approach — API 29 provides excellent WebView support, covers ~90% of active Android devices, and aligns with NativePHP's recommended minimum.

---

### Q9 — Queue Driver: Redis vs Valkey ✅ RESOLVED

**Answer:** Use **Valkey** in Docker (open-source Redis alternative). **Architecture depends on Q16** (device-local vs server).

**Valkey vs Redis:**

| Feature | Redis | Valkey |
|---------|-------|--------|
| License | SSPL (proprietary since 2024) | BSD 3-Clause (open-source) ✅ |
| API compatibility | Reference | 100% drop-in compatible |
| Docker image | `redis:7` | `valkey/valkey:8` |
| Performance | Baseline | ~10-15% faster (benchmarks) |
| Laravel config | `REDIS_CLIENT=phpredis` | Same — no change needed |
| Managed cloud | All providers | AWS ElastiCache for Valkey, GCP, Azure |

**Change:** Replace `redis:7` with `valkey/valkey:8` in `docker-compose.yml`. Zero Laravel code changes.

> **For NativePHP device-local (Option A — confirmed in Q16):** Use `database` queue driver (SQLite-backed). Valkey only needed for a future server-side deployment (Option B).

---

### Q11 — Pint Preset: Does `laravel` Cover PSR-12? ✅ RESOLVED

**Answer:** **Yes.** The `"preset": "laravel"` in `pint.json` is a **strict superset of PSR-12**.  
It includes all PSR-12 rules plus additional Laravel-specific conventions (trailing commas, array syntax, import ordering, blank lines, etc.). The `laravel` preset is the correct and preferred choice.

---

### Q12 — PHPStan Level ✅ RESOLVED

**Answer:** **Level 5** (from blueprint "Level 5+"). INF-02 updated from Level 6 → Level 5.

---

### Q15 — Reverb vs Pusher ✅ RESOLVED

**Answer:** **Laravel Reverb** for MVP. Pusher Sandbox could work for early public testing.

**Pusher free (Sandbox) tier limits:**
- 100 max concurrent connections
- 200,000 messages/day
- 1 app, no custom domain SSL
- Sufficient for a small beta launch

**Decision:** Reverb for dev/MVP. Consider Pusher Sandbox for a managed small beta. Re-evaluate at scale.

> **Note (Q16 — resolved):** The confirmed architecture is device-local (Option A). On the Android device, `BROADCAST_CONNECTION=log` is set — no Reverb runs on-device for MVP. Reverb runs only in the Docker dev environment.

---

## ✅ Resolved — Previously Blocking

---

### Q3b — Laravel 12 vs 13: Owner Decision ✅ RESOLVED

**Decision: Use Laravel 12.**

`nativephp/mobile` v3.0.4 declares `illuminate/contracts ^10.0|^11.0|^12.0` — it does **not** include `^13.0`. Laravel 12 has full, zero-risk compatibility.

All other dependencies (`laravel/ai` v0.3.2, `prism-php/prism` v0.99.x, Livewire 3, Reverb) support Laravel 12.

**All plan files now target Laravel 12.**

---

### Q16 — Database Architecture for NativePHP Mobile ✅ RESOLVED

**Decision: Option A — Device-local SQLite (as recommended by NativePHP Mobile docs).**

SQLite runs on-device as a file-based database with zero server process. NativePHP Mobile explicitly recommends this approach and manages the SQLite file path automatically. All Laravel migrations, Eloquent models, and queries work identically on SQLite — no code changes needed versus the PostgreSQL dev environment.

**What this means for the build:**

| Environment | Database | Queue | Broadcast |
|-------------|----------|-------|-----------|
| Docker dev | PostgreSQL 16 | `database` (Valkey optional) | Reverb |
| Android device | SQLite (on-device) | `database` (SQLite `jobs` table) | `log` (no Reverb on-device for MVP) |

**Queue on-device:** `QUEUE_CONNECTION=database` — the `jobs` table lives in SQLite. A queue worker runs as a background process started by `NativeAppServiceProvider` (documented in MOB-01 and VOICE-03).

**Hybrid Option B preserved:** The plans retain all documentation for a future hybrid server architecture (centralised PostgreSQL + Reverb). This path can be adopted later without structural rework. See MOB-01 Section 3 for the SQLite ↔ PostgreSQL migration note.

---

## 🔵 Deferred (Confirmed by Owner)

| # | Question | Status |
|---|----------|--------|
| Q6 | Missing UI-01 ticket | Deferred — "will find out later" |
| Q10 | User onboarding flow | Deferred — "will build later" |
| Q13 | Android bundle ID | Placeholder `com.dost.app` OK for now |
| Q14 | Brand colour scheme | Deferred — design scheme coming later |

---

## Summary Table

| # | Question | Status | Priority |
|---|----------|--------|----------|
| Q1 | Which Laravel AI SDK? | ✅ `laravel/ai` v0.3.2 | Resolved |
| Q2 | TTS provider? | ✅ Web Speech API (free MVP) | Resolved |
| Q3 | NativePHP v3 package name? | ✅ `nativephp/mobile` ⚠️ L12 only | Resolved |
| Q3b | Laravel 12 vs 13 — final decision? | ✅ Laravel 12 confirmed | Resolved |
| Q4 | DB rows after cleanup? | ✅ Option C (keep row, null path) | Resolved |
| Q5 | Gemini: AI Studio vs Vertex? | ✅ Google AI Studio key | Resolved |
| Q6 | Missing UI-01 ticket? | 🔵 Deferred | Low |
| Q7 | Conversation persistence? | ✅ New per day | Resolved |
| Q8 | Android min SDK? | ✅ API 29 (Android 10+) | Resolved |
| Q9 | Redis or alternative? | ✅ Valkey (open-source) | Resolved |
| Q10 | Onboarding flow? | 🔵 Deferred | Low |
| Q11 | Pint preset covers PSR-12? | ✅ Yes, `laravel` is PSR-12 superset | Resolved |
| Q12 | PHPStan level? | ✅ Level 5 | Resolved |
| Q13 | Android bundle ID? | 🔵 Placeholder OK | Low |
| Q14 | Brand colour scheme? | 🔵 Design scheme coming | Low |
| Q15 | Reverb vs Pusher? | ✅ Reverb for MVP | Resolved |
| Q16 | SQLite vs PostgreSQL architecture? | ✅ Option A — SQLite device-local | Resolved |
|--------|---------|--------|
