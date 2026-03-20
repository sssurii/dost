# Project Blueprint: "Dost" - AI Voice Tutor for Indian Learners

## 1. Project Vision & Context
**Objective:** Build a voice-based English tutor specifically for Indian users with low confidence.
**Core Philosophy:** Focus on "Indian English" fluency and comfort. The AI should be encouraging and **must not** prioritize grammar correction, but rather the act of speaking without fear.
**Platform:** Android First (NativePHP Mobile).
**Interaction Model:** File-based "Walkie-Talkie" (Record -> Send -> Process -> Play).

---

## 2. Technical Stack & Infrastructure Specifications

### 2.1 Backend & Orchestration
* **Framework:** Laravel 13.x
* **Docker Network:** Static subnet `15.15.0.0/16`, Gateway `15.15.0.1`.
* **Database:** PostgreSQL.
* **Real-time:** Laravel Reverb (WebSockets) for UI state changes.
* **Quality Suite:** Laravel Pint, Larastan (Level 5+), PHPMD, Pest.

### 2.2 Mobile & Frontend
* **Wrapper:** NativePHP Air (v3) for Android.
* **Plugins:** `nativephp/mobile-microphone`, `nativephp/mobile-device`.
* **Frontend:** Livewire 3 + Tailwind CSS (Mobile-first).
* **Auth:** Laravel Breeze (Livewire functional).

### 2.3 AI & Voice Pipeline
* **Orchestration:** Laravel AI SDK.
* **LLM Model:** Gemini 2.5 Flash (Optimized for cost/latency).
* **Persona:** Encouraging Indian English Tutor.
* **Audio Logic:** Local file persistence (storage/app/public/recordings), 2-day auto-deletion policy (configurable).

---

## 3. Detailed Execution Roadmap (Tickets)

### Phase 1: Infrastructure (The Bedrock)

#### **Ticket INF-01: Dockerized Laravel 13.x Setup**
* **Context:** Ensure the environment is isolated and mirrors production-grade networking.
* **Details:**
    * Set up `docker-compose.yml`.
    * Define network `app-network` with subnet `15.15.0.0/16`.
    * Containers: `laravel.test` (PHP 8.3/8.4), `postgres` (16+), `reverb`.
    * Verify connectivity to gateway `15.15.0.1`.
* **Complexity:** 2 | **Estimate:** 4h

#### **Ticket INF-02: Code Quality Toolchain**
* **Context:** Strict enforcement of standards from Day 1.
* **Details:**
    * Configure `pint.json` for Laravel presets.
    * Set `phpstan.neon` to Level 5.
    * Create a `scripts` section in `composer.json` to run `test`, `pint`, and `analyze` in one command.
* **Complexity:** 1 | **Estimate:** 2h

#### **Ticket INF-03: MCP Documentation Sync**
* **Context:** Allow AI agents to reference correct package versions.
* **Details:**
    * Set up Model Context Protocol (MCP) for the project.
    * Index docs for: Laravel AI SDK, NativePHP Mobile, and Gemini 2.x Live API.
* **Complexity:** 3 | **Estimate:** 3h

---

### Phase 2: Mobile & Auth (Android Entry)

#### **Ticket MOB-01: NativePHP Android Bootstrapping**
* **Context:** Preparing for Android deployment.
* **Details:**
    * Install `nativephp/mobile`.
    * Verify the `Jump` app connection.
    * Register `nativephp/mobile-microphone` in `NativeAppServiceProvider`.
* **Complexity:** 4 | **Estimate:** 6h

#### **Ticket AUTH-01: Mobile-Optimized Auth**
* **Context:** Low-confidence users need a simple, non-intimidating entry.
* **Details:**
    * Implement Laravel Breeze.
    * Refactor Tailwind views: Bottom-aligned inputs, large touch targets (min 44px), edge-to-edge Android layout.
* **Complexity:** 2 | **Estimate:** 3h

---

### Phase 3: The Voice Core (R&D & Implementation)

#### **Ticket R&D-01: Gemini Audio Compatibility Spike**
* **Context:** NativePHP records in `.m4a` (AAC).
* **Details:**
    * Verify if `Gemini 2.5 Flash` accepts `.m4a` directly via Laravel AI SDK.
    * If not, add `spatie/laravel-ffmpeg` to the stack for server-side conversion to `.wav`.
* **Complexity:** 5 | **Estimate:** 5h

#### **Ticket VOICE-01: Hold-to-Speak Logic**
* **Context:** Walkie-talkie style UX.
* **Details:**
    * Livewire component with `wire:ignore` for the recording button.
    * Use NativePHP `Microphone::record()->start()` and `->stop()`.
    * Save locally to `recordings/{user_id}/` and trigger a `RecordingFinished` event.
* **Complexity:** 4 | **Estimate:** 5h

#### **Ticket VOICE-02: TutorAgent AI Integration**
* **Context:** This is the "Soul" of the app.
* **Details:**
    * System Prompt: "You are a warm, friendly Indian English tutor. Your goal is to build the user's confidence. Use simple Indian English idioms where appropriate. Never correct grammar unless explicitly asked. Encourage the user to keep talking."
    * Use Laravel AI SDK to send the audio file to Gemini and retrieve the text transcript + response.
* **Complexity:** 4 | **Estimate:** 6h

#### **Ticket VOICE-03: AI Response & TTS Playback**
* **Context:** Closing the loop.
* **Details:**
    * Generate AI voice from the text response.
    * Play via NativePHP audio player.
    * UI State: Disable the mic button until the AI finishes speaking (MVP constraint).
* **Complexity:** 3 | **Estimate:** 4h

---

### Phase 4: Analytics & Maintenance

#### **Ticket DATA-01: Pruning & Privacy Policy**
* **Context:** Users value privacy and storage space.
* **Details:**
    * Create a `CleanupAudio` command.
    * Logic: `Audio::where('created_at', '<', now()->subDays(2))->delete()`.
    * Add a setting in the User profile to toggle between 1, 2, or 7 days.
* **Complexity:** 1 | **Estimate:** 2h

#### **Ticket UI-02: Progress Dashboard**
* **Context:** Gamifying confidence.
* **Details:**
    * Display "Total Minutes Spoken."
    * Show a weekly growth chart comparing current week to last week.
* **Complexity:** 3 | **Estimate:** 5h

---

## 4. Operational Guardrails (For the LLM)
1.  **Latency:** Every decision must favor speed. Use local storage over S3 for the MVP.
2.  **Formatting:** Use Tailwind's `v3` classes. Follow Laravel 12's functional Livewire patterns.
3.  **Safety:** Ensure the AI agent does not generate discouraging content.
