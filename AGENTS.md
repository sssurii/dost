> **`AGENTS.md` is the single source of truth for all project-specific conventions.**
> In agent mode: read this file in full before starting any task.

---

=== workflow rules ===
## File Navigation Priority
Stop as soon as you have what you need — follow this order:
1. **Attached files first** — read directly; never search for something already in context.
2. **Derive path from code already read** — `use` statements give you the exact path. `use App\Livewire\Voice\RecordingButton;` → `app/Livewire/Voice/RecordingButton.php`. No search needed.
3. **`grep_search` by known symbol** — when you know a class name, method, or string it contains, grep for it.
4. **`file_search` as a last resort** — only when none of the above applies.

## Before Starting Any Task
Act as a senior engineer from the start:
- Identify all layers affected (model, migration, event, listener, job, Livewire component, Pest tests).
- Think through edge cases and failure paths **before** writing any code.
- Validate the planned approach upfront — not after implementation.
- If intent or scope is unclear, **ask** — never assume. Only ask what cannot be answered from attached files or the codebase.

## After Finishing Any Changes
**Final review pass before running quality checks:**
- Re-read all changed files and verify edge cases and failure paths are handled.
- Confirm no N+1 query risks were introduced.
- Check for dependent files missed (migration ↔ model, event ↔ listener, broadcast channel registered).
- Tests cover both happy path and failure paths.

**Always run the full quality suite after finishing:**
```bash
docker compose exec app composer check
```

## Keeping Instructions Current
- If a new pattern, convention, or gotcha was discovered during work, **add it** to the relevant section in this file. Avoid duplicates and keep additions concise.
- If the detail is too long for here, add it to `plan/` — only when explicitly requested or genuinely needed for future reference.
## Dead Code Cleanup
- When migrating or refactoring, identify and remove unused classes, events, listeners, jobs, and imports.
- Before deleting any file, use `grep_search` to check for references to the class or file name across the codebase.
- List the files to be removed and confirm with the user before deletion.

---

=== project context ===
# Dost — AI Voice Tutor for Indian Learners

## Project Identity
**Dost** is an Android-first app that gives Indian learners a safe, encouraging space to practice spoken English. The AI tutor ("Dost") responds with warmth, uses Indian English idioms, and **never corrects grammar**. The goal is confidence, not perfection.

## Core Philosophy
- **Encouragement over correction** — The AI must never say something is wrong.
- **Indian English warmth** — Use idioms like "yaar", "achha", "wah!" where appropriate.
- **Short AI responses** — 2–3 sentences max, always ending with a follow-up question.
- **Respond to intent, never to words** — React to meaning, not pronunciation or grammar.

## Stack Summary
| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4 |
| Database | **PostgreSQL** (not SQLite) |
| Real-time | Laravel Reverb (WebSockets) |
| Frontend | Livewire 3 + Tailwind CSS v3 |
| Mobile | NativePHP Air v3 (Android-first) |
| AI / LLM | `laravel/ai` → Gemini 2.5 Flash |
| Auth | Laravel Breeze (Livewire stack) |
| Quality | Pint + Larastan Level 5+ + PHPMD + Pest |

## Voice Pipeline Architecture
```
User holds mic button (NativePHP Microphone)
    │
    ▼
RecordingFinished event dispatched
    │
    ▼
ProcessRecording listener (queued on 'ai' queue)
    │
    ▼
TutorProcessor service → TutorAgent (laravel/ai)
    │  sends audio to Gemini 2.5 Flash
    ▼
Recording::markAsCompleted(transcript, response)
    │
    ▼
AiResponseReady broadcast event (Reverb → Livewire)
    │
    ▼
GenerateTtsAudio job → Web Speech API (client-side MVP)
```
## Key Domain Rules
- Audio files stored at `storage/app/public/recordings/{user_id}/` in `.m4a` format.
- Auto-delete after 2 days by default; configurable per-user (1, 2, or 7 days).
- Mic button **disabled** while AI is processing or speaking.
- Never block the UI during AI processing — always use queued jobs.
- Use Reverb to push state changes (`AiResponseReady`) back to the Livewire component.
- `laravel/ai` SDK manages conversation history via `agent_conversations` and `agent_conversation_messages` — do **not** create custom conversation models.

---

=== project enhancements ===
These rules extend or override the Boost-generated sections that follow inside the tags.
## Additional Packages (extends Foundation Rules)
- laravel/ai (AI) - v0
- livewire/livewire (LIVEWIRE) - v3
- nativephp/mobile (NATIVEPHP) - v3
## Static Analysis (extends PHP Rules)
- Code must pass Larastan at **Level 5** (`phpstan.neon`). Run `composer analyze` inside the container.
- Code must pass PHPMD. Run `composer mess` inside the container.
- Run `composer check` for the full suite: pint → larastan → phpmd → pest.
- Project enum key examples: `RecordingStatus`, `AiProvider`, `RetentionDays` (TitleCase).
## Database (extends Laravel/Core Rules)
- This project uses **PostgreSQL**. Always write migrations compatible with PostgreSQL — avoid SQLite-specific syntax.
## Queues (extends Laravel/Core Rules)
- AI processing jobs go on the **`ai` queue**. Audio cleanup jobs go on the **`default` queue**.
- Never perform AI inference or audio processing synchronously in a request/response cycle.
## Events & Listeners (extends Laravel/Core Rules)
- Use events and listeners to decouple the voice pipeline stages.
- Key events: `RecordingFinished`, `AiResponseReady`. Never skip them to "simplify" — they are the architecture.
- Queued listeners must implement `ShouldQueue`.
## Tests (extends Pest Rules)
- For Livewire component tests use `Livewire::test(ComponentName::class)`.
- For queued jobs/listeners, use `Queue::fake()` and assert `Queue::assertPushed(JobName::class)`.
- For broadcast events, use `Event::fake()` and assert `Event::assertDispatched(EventName::class)`.
---
=== livewire rules ===
# Livewire 3
- Use Livewire 3 syntax: `#[On]`, `#[Computed]`, `$this->dispatch()` — never Livewire 2 `emit()`.
- `wire:model.live` for real-time binding; `wire:model` (deferred) for forms.
- `wire:ignore` on any DOM element controlled by NativePHP or vanilla JS (mic button, audio player).
- Components in `app/Livewire/` grouped by subdirectory: `Voice/`, `Dashboard/`.
- Keep components single-responsibility. Extract sub-components rather than building monoliths.
- Use `#[Computed]` for derived data — automatically cached per request.
- For real-time server updates, listen to Echo broadcast events with `#[On('echo:channel,EventName')]`.
- Always scaffold with `php artisan make:livewire`.
## Mobile-First Tailwind
- `min-h-screen` and safe-area inset utilities for Android full-screen layouts.
- All interactive touch targets **≥ 44px** (`min-h-11 min-w-11`).
- Primary actions aligned to the **bottom** of the screen for thumb reach.
- Dark, warm palette: `bg-neutral-900`, `text-amber-400`. Do not use the default light theme.
---
=== nativephp rules ===
# NativePHP Mobile (Android)
- Target: **Android via NativePHP Air v3**.
- Recording: `Microphone::record()->start()` / `->stop()`. Output format: `.m4a` (AAC).
- Register plugins (`mobile-microphone`, `mobile-device`) in `NativeAppServiceProvider`.
- `wire:ignore` on any Livewire element managed by NativePHP JS interop.
- Forward NativePHP native events to Livewire via `$wire.dispatch()` in the JS bridge.
- Test layout in both NativePHP WebView and a regular browser during development.
- Edge-to-edge Android layout: handle status bar and navigation bar areas with inset utilities.
---
=== laravel/ai rules ===
# laravel/ai SDK
- **Never** use the raw Gemini API — always go through `laravel/ai`.
- Create agents: `php artisan make:agent {Name}`.
- Provider: `Laravel\Ai\Enums\Lab::Gemini`. Model: **`gemini-2.5-flash`**.
- Audio attachments: `Files\Audio::fromStorage($path, disk: 'local')`.
- `RemembersConversations` trait for conversation context (e.g. `TutorAgent`).
- `HasStructuredOutput` for typed responses like `{transcript, response}`.
- SDK owns conversation history (`agent_conversations` / `agent_conversation_messages`) — do **not** build custom models.
- System prompts in `app/Prompts/` or config — never hardcoded inline.
- TutorAgent prompt must enforce: encouragement only, Indian English warmth, 2–3 sentences max, never correct grammar, always end with a question.
- Install: `composer require laravel/ai` → `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` → `php artisan migrate`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `mcp-development` — Use this skill for Laravel MCP development only. Trigger when creating or editing MCP tools, resources, prompts, or servers in Laravel projects. Covers: artisan make:mcp-* generators, mcp:inspector, routes/ai.php, Tool/Resource/Prompt classes, schema validation, shouldRegister(), OAuth setup, URI templates, read-only attributes, and MCP debugging. Do not use for non-Laravel MCP projects or generic AI features without MCP.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
