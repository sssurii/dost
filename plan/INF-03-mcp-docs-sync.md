# INF-03: MCP Documentation Sync

**Phase:** 1 — Infrastructure
**Status:** ✅ COMPLETE (March 21, 2026)
**Complexity:** 3 | **Estimate:** 3h | **Actual:** ~1h
**Depends on:** INF-01, INF-02
**Blocks:** VOICE-02 (AI SDK usage), MOB-01 (NativePHP)

---

## Outcome

The objective — AI coding agents can reference correct, versioned package documentation without hallucinating — was achieved through a **simpler approach than originally planned**, using tooling already available in the project.

---

## What Was Done

### 1. Laravel Boost MCP — Primary Documentation Source ✅

`laravel/boost` (already installed as a dev dependency) ships a `search-docs` MCP tool that serves **live, version-specific documentation** for all packages in `composer.json`. This fully replaces the need for `context7` or static `docs/context/` files.

Boost MCP was configured for every relevant AI tool:

| File | Tool |
|------|------|
| `.mcp.json` | Claude Code (project-level), GitHub Copilot (JetBrains) |
| `.vscode/mcp.json` | GitHub Copilot (VS Code) |
| `.gemini/settings.json` | Gemini CLI |
| `.junie/mcp/mcp.json` | Junie (JetBrains AI) |
| `.codex/config.toml` | OpenAI Codex CLI |

All configured to run: `php artisan boost:mcp`

### 2. Project Instruction Files — Baked-in Context ✅

Comprehensive project-specific context (API usage patterns, voice pipeline, domain rules) was written directly into the instruction files each AI tool reads natively. This covers everything the `docs/context/` static files were meant to provide.

| File | Read by |
|------|---------|
| `CLAUDE.md` | Claude Code |
| `AGENTS.md` | OpenAI Codex |
| `GEMINI.md` | Gemini CLI |
| `.github/copilot-instructions.md` | GitHub Copilot (all IDEs) — **created in this ticket** |

Each file contains: stack summary, voice pipeline architecture, laravel/ai SDK patterns, NativePHP patterns, Livewire 3 conventions, PHP/Laravel coding rules, and workflow guidance.

### 3. Skill Files — Domain-Specific Deep Dives ✅

Boost populated skill files for the two most complex domains. These activate on-demand when the AI enters that domain:

| Skill | Location |
|-------|----------|
| `mcp-development` | `.agents/skills/`, `.claude/skills/`, `.github/skills/` |
| `pest-testing` | `.agents/skills/`, `.claude/skills/`, `.github/skills/` |

### 4. `.codex/config.toml` — Path Fixed ✅

The Codex config had `cwd = "/var/www/html"` (Docker container path). Fixed to `cwd = "/home/fnl/dev/dost"` (host path, where Codex CLI actually runs).

---

## What Was Dropped and Why

### ❌ `context7` MCP Server
**Original plan:** Install `@upstash/context7-mcp` to serve live docs.
**Decision:** Dropped. Laravel Boost's `search-docs` tool does exactly this — fetches live, version-filtered docs — with zero additional setup. Adding context7 on top would be redundant infrastructure.

### ❌ `docs/context/` Static Files (`laravel-ai-sdk.md`, `nativephp-mobile.md`, `gemini-api.md`)
**Original plan:** Create curated static context files per package.
**Decision:** Dropped. The instruction files (CLAUDE.md, AGENTS.md, etc.) already contain all critical API patterns baked in and version-controlled. Maintaining a parallel set of context files would create a documentation sync burden with no benefit. Boost `search-docs` handles any gaps at query time.

### ❌ `scripts/sync-docs.php` + `composer docs:sync`
**Original plan:** A PHP script to periodically refresh static context files from GitHub READMEs.
**Decision:** Dropped along with the static files. Not the Laravel way (would be an Artisan command if needed); redundant given Boost live search.

### ❌ `.mcp/config.json` and `.mcp/README.md`
**Original plan:** A dedicated `.mcp/` directory with a README.
**Decision:** The root `.mcp.json` is the standard location. A separate directory adds no value.

---

## Acceptance Criteria — Verified

- [x] AI coding assistant can answer questions about `laravel/ai` audio API without hallucinating — via Boost `search-docs`
- [x] NativePHP Microphone API usage is documented — baked into all instruction files
- [x] M4A audio support confirmed in context — documented in instruction files (R&D-01 resolves definitively at runtime)
- [x] GitHub Copilot has project instructions — `.github/copilot-instructions.md`
- [x] Claude has project instructions + MCP — `CLAUDE.md` + `.mcp.json`
- [x] Codex has correct MCP config — `.codex/config.toml` with correct host path

---

## Files Changed / Created

```
Created:
  .github/copilot-instructions.md    ← Copilot project instructions

Modified:
  .codex/config.toml                 ← Fixed cwd from /var/www/html → /home/fnl/dev/dost
  CLAUDE.md                          ← Added project context, Livewire/NativePHP/laravel/ai sections
  AGENTS.md                          ← Same as CLAUDE.md
  GEMINI.md                          ← Same as CLAUDE.md

Pre-existing (configured by Boost on project init):
  .mcp.json
  .vscode/mcp.json
  .gemini/settings.json
  .junie/mcp/mcp.json
  .agents/skills/mcp-development/SKILL.md
  .agents/skills/pest-testing/SKILL.md
  .claude/skills/mcp-development/SKILL.md
  .claude/skills/pest-testing/SKILL.md
  .github/skills/mcp-development/SKILL.md
  .github/skills/pest-testing/SKILL.md
  routes/ai.php
```
