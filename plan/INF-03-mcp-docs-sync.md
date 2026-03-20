# INF-03: MCP Documentation Sync

**Phase:** 1 — Infrastructure  
**Complexity:** 3 | **Estimate:** 3h  
**Depends on:** INF-01, INF-02  
**Blocks:** VOICE-02 (AI SDK usage), MOB-01 (NativePHP)

---

## 1. Objective

Set up a **Model Context Protocol (MCP)** server that indexes live documentation for:
1. **Laravel AI SDK** (`laravel/ai` — official Laravel AI SDK for Laravel 13)
2. **NativePHP Mobile** (`nativephp/mobile`, microphone plugin)
3. **Gemini 2.x Live API** (Google AI API reference)

This allows AI coding agents (Copilot, Claude, Cursor) to reference **correct, versioned** package documentation instead of hallucinating outdated API signatures.

---

## 2. MCP Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│  Developer IDE (VS Code / Cursor / JetBrains)           │
│                                                         │
│  ┌──────────────┐    MCP Protocol    ┌───────────────┐  │
│  │  AI Coding   │◄──────────────────▶│  MCP Server   │  │
│  │  Assistant   │                    │  (local HTTP) │  │
│  └──────────────┘                    └───────┬───────┘  │
│                                              │          │
│                               ┌──────────────▼───────┐  │
│                               │  Indexed Doc Corpus   │  │
│                               │  ┌──────────────────┐ │  │
│                               │  │ Laravel AI SDK   │ │  │
│                               │  │ NativePHP Mobile │ │  │
│                               │  │ Gemini 2.x API   │ │  │
│                               │  └──────────────────┘ │  │
│                               └──────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Technology Choice

### Option A: `context7` MCP Server (Recommended)
`context7` is an open-source MCP server that fetches and serves live documentation from package registries and GitHub. Zero-infrastructure, runs as a process.

### Option B: `mcp-server-fetch` + Manual Indexing
More control, higher setup cost. Suitable if docs need pre-processing.

### Option C: Local `llms.txt` files
Simplest — create static `llms.txt` context files per package. No server needed.

**Decision: Option A (context7) for live docs + Option C as fallback static context.**

---

## 4. Step-by-Step Implementation

### Step 1 — Install Node & context7 MCP

```bash
# context7 requires Node 18+
node --version

# Install globally (or use npx for zero-install)
npm install -g @upstash/context7-mcp

# Verify
context7-mcp --version
```

### Step 2 — MCP Server Configuration

Create `.mcp/config.json` at project root:

```json
{
  "mcpServers": {
    "context7": {
      "command": "npx",
      "args": ["-y", "@upstash/context7-mcp@latest"],
      "env": {}
    }
  }
}
```

For **VS Code** (`.vscode/mcp.json`):

```json
{
  "servers": {
    "context7": {
      "type": "stdio",
      "command": "npx",
      "args": ["-y", "@upstash/context7-mcp@latest"]
    }
  }
}
```

For **Cursor** (`.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "context7": {
      "command": "npx",
      "args": ["-y", "@upstash/context7-mcp@latest"]
    }
  }
}
```

For **Claude Desktop** (`~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "context7": {
      "command": "npx",
      "args": ["-y", "@upstash/context7-mcp@latest"]
    }
  }
}
```

### Step 3 — Static `llms.txt` Fallback Files

Create `docs/context/` directory for static documentation snapshots:

```
docs/
└── context/
    ├── laravel-ai-sdk.md
    ├── nativephp-mobile.md
    ├── gemini-api.md
    └── README.md
```

**`docs/context/README.md`:**

```markdown
# AI Context Documentation

These files provide project-specific context to AI coding assistants.

## Usage with AI Tools

When asking about API usage, prefix your question with:
> "Using the documentation in docs/context/[file].md, ..."

Or configure your editor's MCP to index these files automatically.

## Files

| File | Source | Last Updated |
|------|--------|-------------|
| `laravel-ai-sdk.md` | https://laravel.com/docs/13.x/ai-sdk | Manual sync |
| `nativephp-mobile.md` | https://nativephp.com/docs/mobile | Manual sync |
| `gemini-api.md` | https://ai.google.dev/gemini-api | Manual sync |
```

### Step 4 — Laravel AI SDK Context File

Create `docs/context/laravel-ai-sdk.md`:

```markdown
# Laravel AI SDK (`laravel/ai`) — Context Reference

## Package
- **Composer:** `laravel/ai`
- **Docs:** https://laravel.com/docs/13.x/ai-sdk
- **GitHub:** https://github.com/laravel/ai

## Installation
```bash
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

## Environment Variables
```dotenv
GEMINI_API_KEY=your-key-from-aistudio.google.com
OPENAI_API_KEY=your-openai-key   # needed for TTS
```

## Provider Enum
```php
use Laravel\Ai\Enums\Lab;

Lab::Gemini;     // for text / multimodal
Lab::OpenAI;     // for TTS
Lab::Anthropic;  // alternative text
```

## Agent (Dost TutorAgent pattern)
```php
// Generate: php artisan make:agent TutorAgent
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Promptable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class TutorAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return 'You are Dost, a warm English speaking partner...';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transcript' => $schema->string()->required(),
            'response'   => $schema->string()->required(),
        ];
    }
}
```

## Prompting with Audio Attachment (Gemini multimodal)
```php
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files;

$response = (new TutorAgent)
    ->forUser($user)
    ->prompt(
        'Respond to this audio message as Dost.',
        provider: Lab::Gemini,
        model: 'gemini-2.5-flash',
        attachments: [
            Files\Audio::fromStorage($recording->path),
        ]
    );

// Structured output
$transcript = $response['transcript'];
$tutorResponse = $response['response'];
$conversationId = $response->conversationId;
```

## Conversation Persistence (built-in)
```php
// New conversation
$response = (new TutorAgent)->forUser($user)->prompt('Hello!');
$conversationId = $response->conversationId;

// Continue existing conversation
$response = (new TutorAgent)
    ->continue($conversationId, as: $user)
    ->prompt('Tell me more.', attachments: [...]);
```

## TTS (OpenAI only in laravel/ai)
```php
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;

$audio = Audio::generate(
    $tutorResponseText,
    provider: Lab::OpenAI,
    voice: 'nova',   // warm female voice
    model: 'tts-1',
);
// Returns binary audio data — save to storage
```

## Provider Feature Support
| Feature | Supported Providers |
|---------|-------------------|
| Text    | OpenAI, Anthropic, Gemini, Groq, Mistral, Ollama, xAI |
| TTS     | OpenAI, ElevenLabs |
| STT     | OpenAI, ElevenLabs, Mistral |
| Files   | OpenAI, Anthropic, Gemini |
```

### Step 5 — NativePHP Mobile Context File

Create `docs/context/nativephp-mobile.md`:

```markdown
# NativePHP Mobile — Context Reference

## Package
- **Composer:** `nativephp/mobile`
- **Docs:** https://nativephp.com/docs/mobile/1/getting-started/introduction
- **GitHub:** https://github.com/NativePHP/mobile

## Key Plugins Used in Dost
- `nativephp/mobile-microphone` — audio recording
- `nativephp/mobile-device` — device info

## Installation
```bash
php artisan native:install
# Select: Android
```

## NativeAppServiceProvider
```php
// app/Providers/NativeAppServiceProvider.php
use Native\Mobile\Facades\Microphone;

public function boot(): void
{
    Microphone::request(); // Request permission on boot
}
```

## Microphone Recording API
```php
use Native\Mobile\Facades\Microphone;

// Start recording
Microphone::record()->start();

// Stop recording — returns the file path
$path = Microphone::record()->stop();
// Path: storage/app/public/recordings/{timestamp}.m4a

// Recording output format: .m4a (AAC, 44.1kHz)
```

## Livewire Integration Pattern
```php
// In Livewire component
public function startRecording(): void
{
    $this->dispatch('start-recording');
}

public function stopRecording(string $filePath): void
{
    // $filePath is the local file path returned by Microphone::stop()
    $this->recordingPath = $filePath;
    $this->processRecording();
}
```

## JavaScript Bridge (in Blade)
```javascript
// wire:ignore on the button — NativePHP handles its own DOM events
document.addEventListener('start-recording', () => {
    Native.Microphone.start();
});

Native.Microphone.onStop((filePath) => {
    @this.stopRecording(filePath);
});
```

## Events
- `Native\Mobile\Events\Microphone\RecordingStarted`
- `Native\Mobile\Events\Microphone\RecordingStopped` — payload: `{path: string}`
```

### Step 6 — Gemini API Context File

Create `docs/context/gemini-api.md`:

```markdown
# Gemini 2.x API — Context Reference

## Model Used in Dost
- **Model:** `gemini-2.5-flash`
- **API Docs:** https://ai.google.dev/gemini-api/docs
- **Pricing:** https://ai.google.dev/pricing

## Audio Input Capabilities
- Gemini 2.5 Flash **natively accepts audio** in the following formats:
  - `audio/wav` ✅
  - `audio/mp3` ✅
  - `audio/aiff` ✅
  - `audio/aac` ✅ (same as M4A audio track)
  - `audio/ogg` ✅
  - `audio/flac` ✅
  - `audio/m4a` — ⚠️ **verify at runtime** (may need `audio/mp4` MIME type)

## Audio File Size Limits
- Max inline base64 size: **20 MB**
- Max via File API: **2 GB**
- For Dost (voice messages): typically 100KB–2MB, inline is fine.

## Rate Limits (Flash)
- Free tier: 15 requests/minute, 1 million tokens/day
- Paid tier: 1000 requests/minute

## TTS (Text-to-Speech)
Gemini 2.5 Flash has native **speech synthesis** capability:
- Endpoint: Gemini Live API (streaming) or single-turn audio generation
- For MVP: Use Google Cloud TTS or Gemini's audio output if available via Prism

## Key Parameters for Dost
```python
# System prompt strategy — no grammar correction, encouragement focused
system_prompt = """
You are a warm, friendly Indian English tutor named Dost.
Your ONLY goal is to build the user's speaking confidence.
- Respond in simple, encouraging Indian English.
- Use phrases like "Wah!", "Very good, yaar!", "Keep going!"
- NEVER correct grammar unless the user explicitly asks.
- Keep responses short (2-4 sentences) for voice playback.
- Ask follow-up questions to keep conversation going.
"""
```

## Cost Estimation for MVP
- Average voice message: ~30 seconds = ~750 tokens (audio)
- Average response: ~100 tokens
- 1000 daily active users × 10 conversations = ~10M tokens/day
- Cost: ~$0.075/day on free tier (within limits), ~$0.80/day paid
```

### Step 7 — Create MCP README

Create `.mcp/README.md`:

```markdown
# MCP (Model Context Protocol) Setup

This project uses context7 MCP to give AI assistants access to accurate documentation.

## Quick Setup

### VS Code / GitHub Copilot
The `.vscode/mcp.json` config is already included. MCP activates automatically.

### Cursor
The `.cursor/mcp.json` config is included. Restart Cursor after cloning.

### Manual Context
If MCP is not available, reference files directly from `docs/context/`:
- `docs/context/laravel-ai-sdk.md`
- `docs/context/nativephp-mobile.md`
- `docs/context/gemini-api.md`

## Updating Documentation

Run the sync script to refresh static context files:
```bash
composer docs:sync
```
```

### Step 8 — Add Docs Sync Script to `composer.json`

Add to the `scripts` section in `composer.json`:

```json
"docs:sync": [
    "@php scripts/sync-docs.php"
]
```

Create `scripts/sync-docs.php`:

```php
<?php
// scripts/sync-docs.php
// Fetches latest documentation snippets for AI context

$sources = [
    'laravel-ai-sdk' => 'https://raw.githubusercontent.com/laravel/ai/main/README.md',
    'nativephp-mobile' => 'https://raw.githubusercontent.com/NativePHP/mobile/main/README.md',
];

foreach ($sources as $name => $url) {
    $content = file_get_contents($url);
    if ($content) {
        // Prepend our curated header, append fetched README
        $existing = file_get_contents("docs/context/{$name}.md");
        // Keep everything up to the first "## Package" section from our file
        $header = substr($existing, 0, strpos($existing, '## Installation'));
        file_put_contents(
            "docs/context/{$name}.md",
            $header . "\n\n---\n\n## Latest README\n\n" . $content
        );
        echo "✓ Synced {$name}\n";
    } else {
        echo "✗ Failed to fetch {$name}\n";
    }
}
```

---

## 5. Directory Structure After Ticket

```
dost/
├── .mcp/
│   ├── config.json
│   └── README.md
├── .vscode/
│   └── mcp.json
├── .cursor/
│   └── mcp.json
├── docs/
│   └── context/
│       ├── README.md
│       ├── laravel-ai-sdk.md
│       ├── nativephp-mobile.md
│       └── gemini-api.md
└── scripts/
    └── sync-docs.php
```

---

## 6. Verification Checklist

- [ ] context7 MCP server starts without error
- [ ] VS Code / Cursor shows MCP context7 as connected
- [ ] AI assistant can resolve `use-context7` queries for Prism PHP
- [ ] `docs/context/laravel-ai-sdk.md` contains correct Gemini audio multimodal API usage
- [ ] `docs/context/nativephp-mobile.md` contains `Microphone::record()->stop()` API
- [ ] `docs/context/gemini-api.md` confirms M4A audio support status

---

## 7. Acceptance Criteria

1. AI coding assistant can answer "How do I send audio to Gemini via Prism?" without hallucinating.
2. NativePHP Microphone API usage is retrievable via MCP.
3. Context files are version-controlled and updated before each major feature sprint.

---

## 8. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| context7 package API changes | Pin to a specific version in `package.json` |
| Gemini 2.5 Flash m4a support unclear | Static context file documents this; R&D-01 resolves it definitively |
| MCP only available in some editors | Static `docs/context/` files work for all editors |
| Documentation drifts from actual API | Schedule quarterly `composer docs:sync` runs |

