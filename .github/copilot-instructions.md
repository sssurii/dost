> **`AGENTS.md` is the single source of truth for all project conventions.**
> In agent mode: read `AGENTS.md` in full before starting any task.
> The summary below is for Copilot chat mode only.

---

# Dost — GitHub Copilot Instructions

## Project Identity

**Dost** is an Android-first app for Indian learners to practice spoken English with an AI tutor. The AI responds with warmth, uses Indian English idioms ("yaar", "achha", "wah!"), and **never corrects grammar**. Goal: confidence, not perfection.

## Core Philosophy

- **Encouragement over correction** — the AI must never imply something is wrong.
- **Short AI responses** — 2–3 sentences max, always ending with a follow-up question.
- **Respond to intent, never to words** — meaning matters, not pronunciation or grammar.

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4 |
| Database | **PostgreSQL** (never SQLite) |
| Real-time | Laravel Reverb (WebSockets) |
| Frontend | Livewire 3 + Tailwind CSS v3 |
| Mobile | NativePHP Air v3 (Android-first) |
| AI / LLM | `laravel/ai` → Gemini 2.5 Flash |
| Auth | Laravel Breeze (Livewire stack) |
| Quality | Pint + Larastan Level 5+ + PHPMD + Pest |

## Voice Pipeline (the architecture — never shortcut it)

```
NativePHP Microphone → RecordingFinished event
    → ProcessRecording listener (queued, 'ai' queue)
    → TutorProcessor → TutorAgent (laravel/ai, Gemini 2.5 Flash)
    → Recording::markAsCompleted(transcript, response)
    → AiResponseReady broadcast (Reverb → Livewire)
    → Web Speech API TTS (client-side)
```

## Key Domain Rules

- Audio stored at `storage/app/public/recordings/{user_id}/` in `.m4a` format.
- Auto-delete after 2 days (configurable per-user: 1, 2, or 7 days).
- Mic button **disabled** while AI is processing or speaking.
- Never perform AI work synchronously — always queued jobs.
- `laravel/ai` owns conversation history (`agent_conversations` / `agent_conversation_messages`) — do **not** build custom conversation models.

---

## PHP Conventions

- Always use curly braces for control structures, even single-line.
- Use PHP 8 constructor property promotion: `public function __construct(public Foo $foo) {}`
- Empty zero-param constructors are not allowed (unless private).
- Always explicit return type declarations.
- Enum keys must be TitleCase: `RecordingStatus`, `AiProvider`, `RetentionDays`.
- PHPDoc blocks over inline comments. Add array shape types where useful.
- Code must pass **Larastan Level 5** (`composer analyze`) and **PHPMD** (`composer mess`).

## Laravel Conventions

- Use `php artisan make:` for all generated files. Pass `--no-interaction`.
- Generic PHP classes: `php artisan make:class`.
- Prefer `Model::query()` over `DB::`. No raw queries unless truly necessary.
- Eager-load relationships — no N+1 risks.
- Always use Form Request classes for validation (never inline).
- Named routes and `route()` for all URL generation.
- `env()` only in config files — use `config('key')` everywhere else.
- AI jobs on the **`ai` queue**. Cleanup jobs on **`default`**.
- Events and listeners decouple pipeline stages. Key events: `RecordingFinished`, `AiResponseReady`. Never skip them.
- Queued listeners must implement `ShouldQueue`.

## Livewire 3

- Use `#[On]`, `#[Computed]`, `$this->dispatch()` — never Livewire 2 `emit()`.
- `wire:model.live` for real-time, `wire:model` for forms.
- `wire:ignore` on any element managed by NativePHP or vanilla JS.
- Components in `app/Livewire/` grouped by subdirectory: `Voice/`, `Dashboard/`.
- Scaffold with `php artisan make:livewire`.
- Real-time updates via `#[On('echo:channel,EventName')]`.
- Mobile-first Tailwind: `min-h-screen`, dark warm palette (`bg-neutral-900`, `text-amber-400`), touch targets ≥ 44px (`min-h-11`), primary actions at the bottom.

## NativePHP Mobile

- Recording: `Microphone::record()->start()` / `->stop()`. Output is `.m4a`.
- Register plugins (`mobile-microphone`, `mobile-device`) in `NativeAppServiceProvider`.
- NativePHP JS events forwarded to Livewire via `$wire.dispatch()`.
- `wire:ignore` on any element with NativePHP JS interop.

## laravel/ai SDK

- **Never** use the raw Gemini API — always go through `laravel/ai`.
- Create agents: `php artisan make:agent {Name}`.
- Provider: `Laravel\Ai\Enums\Lab::Gemini`. Model: `gemini-2.5-flash`.
- Audio attachments: `Files\Audio::fromStorage($path, disk: 'local')`.
- `RemembersConversations` trait for conversation context (e.g. `TutorAgent`).
- `HasStructuredOutput` for typed responses like `{transcript, response}`.
- System prompts in `app/Prompts/` or config — never hardcoded.
- Install: `composer require laravel/ai` → publish provider → `php artisan migrate`.

---

## Workflow

### File Navigation (stop at first match)
1. Attached files first — never search for something already in context.
2. Derive path from `use` statements — `use App\Livewire\Voice\RecordingButton` → `app/Livewire/Voice/RecordingButton.php`.
3. `grep_search` by known symbol.
4. File search only as last resort.

### Before Starting Any Task
- Identify all affected layers (model, migration, event, listener, job, Livewire, tests).
- Think through edge cases and failure paths **before** writing code.
- Ask if intent is unclear — never assume.

### After Finishing Any Changes
- Re-read all changed files; verify edge cases handled.
- No N+1 risks introduced.
- Dependent files not missed (migration ↔ model, event ↔ listener, broadcast channel registered).
- Tests cover happy path AND failure paths.

### Quality Suite (always run before done)
```bash
composer check
```
This runs: Pint style check → Larastan analysis → PHPMD → Pest tests.

### Dead Code Cleanup
- Before deleting any file, grep for class/file references across the codebase.
- List files to remove and confirm before deletion.

---

## MCP Tools Available

The **Laravel Boost MCP** server is connected (configured in `.vscode/mcp.json`). Use its tools:

- `search-docs` — search version-specific Laravel ecosystem documentation before making changes.
- `database-schema` — inspect table structure before writing migrations.
- `database-query` — read from the database without tinker.
- `browser-logs` — read browser console errors.
- `get-absolute-url` — resolve the correct dev server URL.

Always run `search-docs` before implementing anything in `laravel/ai`, `livewire`, `nativephp`, or `laravel/reverb`.

