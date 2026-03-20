# Dost — Master Execution Plan

**Project:** Dost — AI Voice Tutor for Indian Learners  
**Last updated:** March 2026  
**Status:** ⚠️ 2 blocking decisions needed before starting Phase 1 (see Section 3)

---

## 1. What We're Building

A **voice-based English confidence trainer** for Indian users.  
Core loop: User holds a button → speaks → releases → AI (Gemini) listens and responds with encouragement → voice plays back.

**The key insight:** The app never corrects grammar. It only encourages. Users speak more because they feel safe.

**Platform:** Android (NativePHP Mobile — Laravel app runs on-device)  
**Stack:** Laravel 12/13 · NativePHP Mobile v3 · Livewire 3 · Tailwind CSS · Gemini 2.5 Flash via `laravel/ai` · SQLite (device) / PostgreSQL (dev) · Reverb

---

## 2. Two Decisions Needed Before Starting

> **These are blocking. Resolve them before touching a keyboard.**

| # | Decision | Options | Recommendation |
|---|----------|---------|----------------|
| **Q3b** | Laravel version | 12 (NativePHP confirmed) or 13 (untested) | **Use Laravel 12** |
| **Q16** | App architecture | A: Device-local (SQLite, no server) or B: Hybrid (remote server, PostgreSQL) | **Option A (device-local)** |

**Why it matters:**  
`nativephp/mobile` v3 only declares `illuminate/contracts ^10\|^11\|^12`. Laravel 13 compatibility is unverified.  
For Option A (recommended), the database is SQLite on-device — no infra needed, offline-capable.  
For Option B, you need a server, PostgreSQL, and a different architecture pattern.

**See:** `plan/QUESTIONS.md` → Q3b and Q16 for full detail.

---

## 3. Execution Order

### Phase 1 — Infrastructure (9h)

| # | Ticket | File | Est. | Depends on | Status |
|---|--------|------|------|-----------|--------|
| 1 | Dockerized Laravel Setup | `INF-01` | 4h | Nothing | 🔲 Not started |
| 2 | Code Quality Toolchain | `INF-02` | 2h | INF-01 | 🔲 Not started |
| 3 | MCP Documentation Sync | `INF-03` | 3h | INF-01, INF-02 | 🔲 Not started |

**Phase 1 goal:** `docker compose up -d` gives you a running Laravel 12 app with Postgres (dev), Reverb, PHPStan Level 5, Pest, and Pint all wired.

---

### Phase 2 — Mobile & Auth (9h)

| # | Ticket | File | Est. | Depends on | Status |
|---|--------|------|------|-----------|--------|
| 4 | NativePHP Android Bootstrap | `MOB-01` | 6h | INF-01, INF-02 | 🔲 Not started |
| 5 | Mobile-Optimised Auth | `AUTH-01` | 3h | MOB-01 | 🔲 Not started |

**Phase 2 goal:** An APK that loads the app, shows a warm dark login screen, and authenticates users. SQLite configured for the Android build.

---

### Phase 3 — Voice Core (20h)

| # | Ticket | File | Est. | Depends on | Status |
|---|--------|------|------|-----------|--------|
| 6 | Gemini Audio Spike | `RND-01` | 5h | INF-01–03 | 🔲 Not started |
| 7 | Hold-to-Speak Recording | `VOICE-01` | 5h | MOB-01, AUTH-01, RND-01 | 🔲 Not started |
| 8 | TutorAgent AI Integration | `VOICE-02` | 6h | VOICE-01, RND-01 | 🔲 Not started |
| 9 | TTS Playback | `VOICE-03` | 4h | VOICE-02, MOB-01 | 🔲 Not started |

**Phase 3 goal:** The complete voice loop works on a real Android device.

---

### Phase 4 — Polish (7h)

| # | Ticket | File | Est. | Depends on | Status |
|---|--------|------|------|-----------|--------|
| 10 | Audio Pruning & Privacy | `DATA-01` | 2h | VOICE-01, AUTH-01 | 🔲 Not started |
| 11 | Progress Dashboard | `UI-02` | 5h | VOICE-01, VOICE-02, AUTH-01 | 🔲 Not started |

**Phase 4 goal:** Users can see their progress, trust that their data is handled, and feel motivated to keep talking.

**Total: ~36 hours**

---

## 4. Critical Path

> This chain must not slip. Every ticket here blocks everything after it.

```
INF-01 (Docker)
  └── MOB-01 (NativePHP Android)
        └── RND-01 (Gemini Audio Spike)
              └── VOICE-01 (Hold-to-Speak)
                    └── VOICE-02 (TutorAgent)
                          └── VOICE-03 (TTS Playback)  ← MVP DONE
```

Everything else (INF-02, INF-03, AUTH-01, DATA-01, UI-02) is important but not on the critical path.

---

## 5. Quick-Start Commands

> From zero to a running dev environment. Run in order.

```bash
# 1. Create Laravel 12 project
composer create-project laravel/laravel:^12.0 dost
cd dost

# 2. Start Docker environment
docker compose up -d

# 3. Install laravel/ai SDK
docker compose exec laravel.test composer require laravel/ai
docker compose exec laravel.test php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"

# 4. Run all migrations
docker compose exec laravel.test php artisan migrate

# 5. Create storage symlink + recordings directory
docker compose exec laravel.test php artisan storage:link
docker compose exec laravel.test mkdir -p storage/app/public/recordings

# 6. Install NativePHP Mobile
docker compose exec laravel.test composer require nativephp/mobile
docker compose exec laravel.test php artisan native:install

# 7. Start queue workers
docker compose exec laravel.test php artisan queue:work --queue=ai,default &

# 8. Verify everything is green
docker compose exec laravel.test php artisan test
docker compose exec laravel.test ./vendor/bin/pint --test
docker compose exec laravel.test ./vendor/bin/phpstan analyse
```

---

## 6. Environment Setup Checklist

Complete these **before Day 1** of development:

### System Requirements

- [ ] Docker Engine 26+ installed
- [ ] Docker Compose plugin v2.24+ installed
- [ ] PHP 8.3+ on host (for initial Composer commands)
- [ ] Composer 2.7+ installed
- [ ] Node.js 22+ (for frontend assets via `npm run build`)
- [ ] JDK 17 or 21 installed (`java -version`)
- [ ] Android SDK installed with `ANDROID_HOME` set
- [ ] `adb` and `sdkmanager` in PATH
- [ ] Android SDK components: `platforms;android-34`, `build-tools;34.0.0`, `platform-tools`

### API Keys (get these first)

| Key | Where | Used for |
|-----|-------|---------|
| `GEMINI_API_KEY` | https://aistudio.google.com → "Get API key" | Gemini 2.5 Flash (AI responses) — free tier |
| `OPENAI_API_KEY` | https://platform.openai.com/api-keys | OpenAI TTS — **only needed for VOICE-03 Tier 2 upgrade, not for MVP** |

### `.env` Minimum for MVP

```dotenv
APP_NAME=Dost
APP_KEY=                    # php artisan key:generate
APP_URL=http://localhost

# Dev Docker database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=15.15.0.3
DB_PORT=5432
DB_DATABASE=dost
DB_USERNAME=dost_user
DB_PASSWORD=secret

# Gemini AI
GEMINI_API_KEY=your-key-here

# Queue
QUEUE_CONNECTION=database

# Broadcast (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=dost
REVERB_APP_KEY=dost-key
REVERB_APP_SECRET=dost-secret
REVERB_HOST=15.15.0.4
REVERB_PORT=8080
REVERB_SCHEME=http
```

### `.env.mobile` Minimum (Android build)

```dotenv
APP_NAME=Dost
DB_CONNECTION=sqlite       # On-device SQLite
QUEUE_CONNECTION=database  # SQLite-backed queue
BROADCAST_CONNECTION=log   # No Reverb on-device for MVP
GEMINI_API_KEY=your-key-here
```

---

## 7. Key Technical Decisions (All Confirmed)

| Decision | Confirmed Choice |
|----------|-----------------|
| AI SDK | `laravel/ai` v0.3.2 (wraps `prism-php/prism`) |
| LLM | Gemini 2.5 Flash via `Lab::Gemini` |
| TTS (MVP) | Web Speech API — free, `en-IN`, on-device |
| TTS (Upgrade) | OpenAI `tts-1` via `laravel/ai` — when quality matters |
| Database (Dev) | PostgreSQL 16 (Docker) |
| Database (Android) | SQLite (on-device, NativePHP recommended) |
| Queue (Dev/Android) | `database` driver |
| Queue (Production) | Valkey 8 (open-source Redis fork) |
| NativePHP package | `nativephp/mobile ^3.0` (GitHub: mobile-air) |
| Laravel version | **12** (NativePHP-confirmed) — pending Q3b decision |
| PHPStan | Level 5 |
| Pint preset | `laravel` (PSR-12 superset) |
| WebSockets | Laravel Reverb (MVP) |
| Conversation scope | New per day |
| Audio cleanup | Keep DB row, null paths after `expires_at` |
| Android min SDK | API 29 (Android 10+) |

---

## 8. MVP Definition — "Done" Criteria

> The MVP is complete when all of these are true on a physical Android device:

- [ ] App installs via APK built with `php artisan native:build android`
- [ ] User can register and log in
- [ ] Holding the mic button starts recording (microphone permission granted)
- [ ] Releasing the button sends audio to Gemini
- [ ] Dost responds with an encouraging message in Indian English
- [ ] Response is spoken aloud via Web Speech API (`en-IN` voice)
- [ ] Mic is disabled during AI processing and TTS playback
- [ ] Mic re-enables after TTS finishes
- [ ] User can see their total speaking minutes
- [ ] Audio files are automatically deleted after 2 days (configurable)

---

## 9. Post-MVP Roadmap (Prioritised)

1. **VOICE-03 Tier 2** — OpenAI TTS via `laravel/ai` for better voice quality (~1 day)
2. **Onboarding flow** (UX-01) — hold-to-speak tutorial, mic permission explainer
3. **Valkey queue** — replace `database` driver with Valkey for production reliability
4. **Conversation persistence** — switch from daily-reset to per-level/per-week scope
5. **Gemini Live API** — real-time streaming to eliminate walkie-talkie delay
6. **Push notifications** — streak reminders via `nativephp/mobile`
7. **Conversation export** — let users download their recordings
8. **Multiple practice modes** — Job Interview, Daily Chat, etc.
9. **Custom "Dost" voice** — ElevenLabs once brand identity is confirmed (via `laravel/ai`)

