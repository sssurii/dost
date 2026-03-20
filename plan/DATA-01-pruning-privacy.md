# DATA-01: Audio Pruning & Privacy Policy

**Phase:** 4 — Analytics & Maintenance  
**Complexity:** 1 | **Estimate:** 2h  
**Depends on:** VOICE-01 (Recording model + expires_at), AUTH-01 (user retention preference)  
**Blocks:** Nothing (maintenance feature)

---

## 1. Objective

Implement automatic audio file cleanup to:
1. Respect user privacy (audio files don't linger forever)
2. Free device/server storage after the user's chosen retention window
3. Give users control over their data retention (1, 2, or 7 days)
4. **Preserve text history and statistics** — `transcript`, `ai_response_text`, and `duration_seconds` survive cleanup so the progress dashboard (UI-02) keeps working

> **Q4 Resolved — Option C:** Keep the `recordings` DB row. Delete the audio files. Null out `path` and `ai_response_audio_path` columns. This preserves all analytics data (total minutes spoken, conversation history, streak counts) while freeing storage.

---

## 2. What Gets Cleaned vs What Survives

| Item | Action | Reason |
|------|--------|--------|
| `storage/.../recordings/{user_id}/*.m4a` | 🗑️ **Delete file** | Free storage, honour privacy |
| `storage/.../responses/{user_id}/*.mp3` | 🗑️ **Delete file** | Free storage |
| `recordings.path` column | `NULL` | File is gone; column should reflect reality |
| `recordings.ai_response_audio_path` column | `NULL` | File is gone |
| `recordings.transcript` | ✅ **Keep** | Text history for conversation context |
| `recordings.ai_response_text` | ✅ **Keep** | Text history |
| `recordings.duration_seconds` | ✅ **Keep** | Progress stats (UI-02) |
| `recordings.status`, `created_at` | ✅ **Keep** | Streak calculation (UI-02) |
| The `recordings` DB row itself | ✅ **Keep** | Never deleted — only files are cleaned |

---

## 3. Step-by-Step Implementation

### Step 1 — Verify `expires_at` is Set Correctly

The `expires_at` field is set in `VOICE-01` when a recording is created:

```php
// In RecordingButton::persistRecording() — already implemented in VOICE-01
Recording::create([
    ...
    'expires_at' => now()->addDays($user->audio_retention_days ?? 2),
]);
```

Verify the `users.audio_retention_days` column exists (added in `AUTH-01`).

### Step 2 — Create the `CleanupAudio` Artisan Command

```bash
php artisan make:command CleanupAudio
```

```php
<?php
// app/Console/Commands/CleanupAudio.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Recording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupAudio extends Command
{
    protected $signature = 'audio:cleanup
                            {--dry-run : Preview what would be cleaned without making changes}
                            {--user= : Clean up a specific user ID only}';

    protected $description = 'Delete expired audio files and null-out paths. Keeps DB rows for stats.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $userId   = $this->option('user');

        $this->info($isDryRun
            ? '🔍 DRY RUN — no changes will be made'
            : '🧹 Starting audio cleanup (Option C: files deleted, rows kept)...'
        );

        // Only target recordings whose audio files have not been cleaned yet
        // (path IS NOT NULL means the file still exists or hasn't been nulled)
        $query = Recording::where('expires_at', '<', now())
            ->where(function ($q) {
                $q->whereNotNull('path')
                  ->orWhereNotNull('ai_response_audio_path');
            });

        if ($userId) {
            $query->where('user_id', (int) $userId);
        }

        $deletedFiles = 0;
        $freedBytes   = 0;
        $nulledRows   = 0;

        $query->chunkById(100, function ($recordings) use (
            $isDryRun,
            &$deletedFiles,
            &$freedBytes,
            &$nulledRows,
        ) {
            foreach ($recordings as $recording) {
                $this->line("  → Recording #{$recording->id} (user {$recording->user_id})");

                $updates = [];

                // 1. Delete the voice recording file (.m4a)
                if ($recording->path) {
                    if (Storage::disk('public')->exists($recording->path)) {
                        $size = Storage::disk('public')->size($recording->path);
                        $freedBytes += $size;

                        if (! $isDryRun) {
                            Storage::disk('public')->delete($recording->path);
                        }

                        $deletedFiles++;
                        $this->line("    ✓ Deleted recording file ({$size} bytes): {$recording->path}");
                    }

                    // Null the path regardless — file may already be missing
                    $updates['path'] = null;
                }

                // 2. Delete the AI TTS response file (.mp3 / .wav)
                if ($recording->ai_response_audio_path) {
                    if (Storage::disk('public')->exists($recording->ai_response_audio_path)) {
                        $size = Storage::disk('public')->size($recording->ai_response_audio_path);
                        $freedBytes += $size;

                        if (! $isDryRun) {
                            Storage::disk('public')->delete($recording->ai_response_audio_path);
                        }

                        $deletedFiles++;
                        $this->line("    ✓ Deleted response file ({$size} bytes): {$recording->ai_response_audio_path}");
                    }

                    $updates['ai_response_audio_path'] = null;
                }

                // 3. Null out path columns in DB — but KEEP the row and all text data
                if (! $isDryRun && ! empty($updates)) {
                    $recording->update($updates);
                    $nulledRows++;
                    $this->line('    ✓ DB paths nulled (transcript + stats preserved)');
                }
            }
        });

        $freedMB = round($freedBytes / 1024 / 1024, 2);

        $this->newLine();
        $this->info('✅ Cleanup complete:');
        $this->table(['Metric', 'Value'], [
            ['Audio files deleted',   $deletedFiles],
            ['DB rows updated (paths nulled)', $nulledRows],
            ['DB rows preserved (text + stats kept)', $nulledRows],
            ['Storage freed',         "{$freedMB} MB"],
        ]);

        if ($isDryRun) {
            $this->warn('(DRY RUN — nothing was actually changed)');
        }

        return self::SUCCESS;
    }
}
```

### Step 3 — Schedule the Command

Update `app/Console/Kernel.php` (or use `routes/console.php` in Laravel 13+):

**Laravel 13+ approach (in `routes/console.php`):**

```php
<?php

use Illuminate\Support\Facades\Schedule;

// Run cleanup at 2 AM daily (low-traffic window)
Schedule::command('audio:cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/audio-cleanup.log'));
```

**Verify the scheduler is running in Docker:**

Add to `supervisord.conf`:

```ini
[program:laravel-scheduler]
command=php /var/www/html/artisan schedule:work
autostart=true
autorestart=true
user=sail
stdout_logfile=/var/www/html/storage/logs/scheduler.log
```

### Step 4 — User Settings: Retention Preference UI

Create a simple settings page component:

```bash
php artisan make:livewire Settings/AudioRetention
```

```php
<?php
// app/Livewire/Settings/AudioRetention.php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
final class AudioRetention extends Component
{
    #[Validate('required|integer|in:1,2,7')]
    public int $retentionDays = 2;

    public function mount(): void
    {
        $this->retentionDays = Auth::user()->audio_retention_days ?? 2;
    }

    public function save(): void
    {
        $this->validate();

        Auth::user()->update([
            'audio_retention_days' => $this->retentionDays,
        ]);

        // Update all PENDING/PROCESSING recordings for this user
        // (already-expired recordings are cleaned at next cron run)
        Auth::user()->recordings()
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->where('expires_at', '>', now())
            ->update([
                'expires_at' => now()->addDays($this->retentionDays),
            ]);

        $this->dispatch('saved');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.audio-retention');
    }
}
```

Create `resources/views/livewire/settings/audio-retention.blade.php`:

```html
<div class="min-h-screen bg-gray-950 px-6 pt-8">

    <div class="max-w-sm mx-auto">
        <h1 class="text-xl font-bold text-white mb-2">Privacy Settings</h1>
        <p class="text-gray-500 text-sm mb-8">
            Control how long your voice recordings are stored.
        </p>

        {{-- Retention Options --}}
        <div class="space-y-3">
            @foreach([1 => 'Delete after 1 day', 2 => 'Delete after 2 days (default)', 7 => 'Keep for 7 days'] as $days => $label)
                <button
                    wire:click="$set('retentionDays', {{ $days }})"
                    class="
                        w-full p-4 rounded-2xl border text-left transition-all
                        {{ $retentionDays === $days
                            ? 'bg-orange-500/10 border-orange-500 text-white'
                            : 'bg-gray-900 border-gray-800 text-gray-400'
                        }}
                    "
                >
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center
                            {{ $retentionDays === $days ? 'border-orange-500' : 'border-gray-600' }}">
                            @if ($retentionDays === $days)
                                <div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div>
                            @endif
                        </div>
                        <span class="text-sm font-medium">{{ $label }}</span>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Privacy Note --}}
        <div class="mt-6 p-4 rounded-2xl bg-gray-900 border border-gray-800">
            <p class="text-gray-500 text-xs leading-relaxed">
                🔒 Your voice recordings are stored securely on our servers
                and automatically deleted after your chosen period.
                We never share your recordings with anyone.
            </p>
        </div>

        {{-- Save Button --}}
        <button
            wire:click="save"
            wire:loading.attr="disabled"
            class="
                mt-6 w-full h-14 rounded-2xl font-semibold text-base
                bg-gradient-to-r from-orange-500 to-rose-500
                text-white shadow-lg shadow-orange-500/25
                active:scale-[0.98] transition-all duration-150
            "
        >
            <span wire:loading.remove wire:target="save">Save Preference</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>

        {{-- Saved Toast --}}
        <div
            x-data="{ show: false }"
            x-on:saved.window="show = true; setTimeout(() => show = false, 2000)"
            x-show="show"
            x-transition
            class="mt-4 text-center text-green-400 text-sm"
        >
            ✓ Saved!
        </div>
    </div>
</div>
```

### Step 5 — Add Route

```php
// routes/web.php
use App\Livewire\Settings\AudioRetention;

Route::middleware(['auth'])->group(function () {
    Route::get('/settings/privacy', AudioRetention::class)->name('settings.privacy');
});
```

### Step 6 — Update User Model (Add Relationship)

```php
// app/Models/User.php

use App\Models\Recording;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function recordings(): HasMany
{
    return $this->hasMany(Recording::class);
}
```

---

## 4. Pest Tests

Create `tests/Feature/CleanupAudioTest.php`:

```php
<?php

use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

describe('CleanupAudio Command', function () {

    beforeEach(function () {
        $this->user = User::factory()->create(['audio_retention_days' => 2]);
        Storage::fake('public');
    });

    it('deletes audio files and nulls paths for expired recordings (Option C)', function () {
        Storage::disk('public')->put('recordings/1/old.m4a', 'audio-data');
        Storage::disk('public')->put('responses/1/response.mp3', 'audio-data');

        $recording = Recording::factory()->create([
            'user_id'                 => $this->user->id,
            'path'                    => 'recordings/1/old.m4a',
            'ai_response_audio_path'  => 'responses/1/response.mp3',
            'transcript'              => 'Hello my name is Raj',
            'ai_response_text'        => 'Wah! Great to meet you Raj!',
            'duration_seconds'        => 12,
            'status'                  => 'completed',
            'expires_at'              => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        // Files deleted
        Storage::disk('public')->assertMissing('recordings/1/old.m4a');
        Storage::disk('public')->assertMissing('responses/1/response.mp3');

        // DB row STILL EXISTS — Option C
        $this->assertDatabaseHas('recordings', ['id' => $recording->id]);

        // Paths nulled
        $fresh = $recording->fresh();
        expect($fresh->path)->toBeNull()
            ->and($fresh->ai_response_audio_path)->toBeNull();

        // Text + stats preserved
        expect($fresh->transcript)->toBe('Hello my name is Raj')
            ->and($fresh->ai_response_text)->toBe('Wah! Great to meet you Raj!')
            ->and($fresh->duration_seconds)->toBe(12);
    });

    it('does not touch non-expired recordings', function () {
        Storage::disk('public')->put('recordings/1/new.m4a', 'audio-data');

        $recording = Recording::factory()->create([
            'user_id'    => $this->user->id,
            'path'       => 'recordings/1/new.m4a',
            'expires_at' => now()->addDay(), // not yet expired
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        Storage::disk('public')->assertExists('recordings/1/new.m4a');
        $this->assertDatabaseHas('recordings', [
            'id'   => $recording->id,
            'path' => 'recordings/1/new.m4a',
        ]);
    });

    it('supports dry-run mode without making changes', function () {
        Storage::disk('public')->put('recordings/1/old.m4a', 'audio-data');

        $recording = Recording::factory()->create([
            'user_id'    => $this->user->id,
            'path'       => 'recordings/1/old.m4a',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists('recordings/1/old.m4a');
        $this->assertDatabaseHas('recordings', [
            'id'   => $recording->id,
            'path' => 'recordings/1/old.m4a', // path unchanged in dry-run
        ]);
    });

    it('skips recordings where paths are already null', function () {
        $recording = Recording::factory()->create([
            'user_id'    => $this->user->id,
            'path'       => null, // already cleaned
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        // Row still exists, nothing changed
        $this->assertDatabaseHas('recordings', ['id' => $recording->id]);
    });
});
```

---

## 5. Verification Checklist

- [ ] `php artisan audio:cleanup` runs without error
- [ ] `php artisan audio:cleanup --dry-run` shows what would be cleaned without changes
- [ ] Expired `.m4a` recording files are deleted from storage
- [ ] Expired `.mp3` TTS response files are deleted from storage
- [ ] `recordings.path` and `recordings.ai_response_audio_path` are `NULL` after cleanup
- [ ] `recordings.transcript`, `recordings.ai_response_text`, `recordings.duration_seconds` are **untouched**
- [ ] DB `recordings` rows are **NOT deleted** — only files and paths are cleaned
- [ ] Non-expired recordings are completely untouched
- [ ] Scheduler runs `audio:cleanup` daily at 2 AM
- [ ] Settings UI allows user to choose 1, 2, or 7 days retention
- [ ] `composer test` passes all cleanup tests

---

## 6. Acceptance Criteria

1. `audio:cleanup` finds all recordings where `expires_at < now()` AND `path IS NOT NULL`.
2. Audio files (`.m4a`) and TTS files (`.mp3`) are deleted from storage.
3. `recordings.path` and `recordings.ai_response_audio_path` are set to `NULL` in the DB.
4. `recordings` row is **kept** — transcript, AI response text, and duration_seconds survive.
5. UI-02 progress dashboard continues to work correctly after cleanup (duration_seconds preserved).
6. User can change retention preference in Settings → Privacy.
7. `--dry-run` previews without making any changes.

---

## 7. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Chunk processing fails mid-way | Use `chunkById` (continues from last ID on re-run); log each failure |
| File missing but path not null | Handle gracefully — null the path anyway (defensive cleanup) |
| User changes retention while files already expired | Cron catches them on next run |
| Scheduler not running (Docker or device) | Add `schedule:work` to `supervisord.conf`; on device NativePHP can schedule it |
| `duration_seconds` NULL for old recordings | UI-02 treats NULL as 0; no crash, just missing data for pre-VOICE-01 recordings |

