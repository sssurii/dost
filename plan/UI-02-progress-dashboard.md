# UI-02: Progress Dashboard

**Phase:** 4 — Analytics & Maintenance  
**Complexity:** 3 | **Estimate:** 5h  
**Depends on:** VOICE-01 (Recording model), VOICE-02 (Conversation + Message models), AUTH-01 (user model)  
**Blocks:** Nothing

---

## 1. Objective

Build a **gamified progress dashboard** that:
1. Shows total minutes spoken (all-time)
2. Displays a weekly speaking growth chart (this week vs. last week)
3. Motivates the user with streaks and positive milestones
4. Integrates into the main app navigation

---

## 2. UX Design Goals

- **Dark theme**, consistent with auth and voice screens
- **Glanceable** — user understands their progress in 3 seconds
- **Celebratory** — milestones and streaks, Indian warmth
- **No raw numbers without context** — "3 minutes" is meaningless; "You spoke 3x more than last week!" is motivating

---

## 3. Metrics to Track

| Metric | Source | Display |
|--------|--------|---------|
| Total minutes spoken | `SUM(recordings.duration_seconds) / 60` | Large hero number |
| This week's minutes | `recordings WHERE created_at >= start_of_week` | Chart bar |
| Last week's minutes | `recordings WHERE created_at >= start_of_last_week AND < start_of_week` | Chart bar (comparison) |
| Daily breakdown (last 7 days) | Per-day aggregation | Bar chart |
| Current streak (consecutive days spoken) | `recordings GROUP BY DATE(created_at)` | Streak counter |
| Total conversations | `COUNT(conversations)` | Secondary stat |

---

## 4. Architecture

```
app/Services/
└── Analytics/
    └── UserProgressService.php    ← All DB queries

app/Livewire/
└── Dashboard/
    └── ProgressDashboard.php     ← Livewire component

resources/views/livewire/dashboard/
└── progress-dashboard.blade.php  ← Chart + stats view
```

---

## 5. Step-by-Step Implementation

### Step 1 — Add `duration_seconds` to Recordings

The `duration_seconds` field was included in the `recordings` table from VOICE-01. We need to populate it.

Update `RecordingButton::persistRecording()` in VOICE-01:

```php
// When the native bridge returns stop, we may get duration info
// If not available from NativePHP, estimate from file size
$durationSeconds = $this->estimateDuration($fileContents, 'audio/mp4');

Recording::create([
    ...
    'duration_seconds' => $durationSeconds,
]);
```

```php
private function estimateDuration(string $fileContents, string $mimeType): int
{
    // Rough estimate: 128kbps AAC ≈ 16KB per second
    $bytes = strlen($fileContents);
    return (int) max(1, round($bytes / (16 * 1024)));
}
```

> **Better approach:** Have the NativePHP JavaScript bridge pass duration along with the file path:
> ```javascript
> Native.Microphone.stop((filePath, durationMs) => {
>     $wire.onRecordingStopped(filePath, Math.round(durationMs / 1000));
> });
> ```

Update `onRecordingStopped(string $nativePath, int $durationSeconds = 0)`.

### Step 2 — Create `UserProgressService`

```bash
mkdir -p app/Services/Analytics
```

Create `app/Services/Analytics/UserProgressService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class UserProgressService
{
    /**
     * Cache TTL for progress data — 15 minutes.
     */
    private const CACHE_TTL = 900;

    public function getProgressData(User $user): ProgressData
    {
        $cacheKey = "user_progress_{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return new ProgressData(
                totalMinutes: $this->getTotalMinutes($user),
                thisWeekMinutes: $this->getWeekMinutes($user, 'this'),
                lastWeekMinutes: $this->getWeekMinutes($user, 'last'),
                dailyBreakdown: $this->getDailyBreakdown($user),
                currentStreak: $this->getCurrentStreak($user),
                totalConversations: $this->getTotalConversations($user),
            );
        });
    }

    public function invalidateCache(User $user): void
    {
        Cache::forget("user_progress_{$user->id}");
    }

    private function getTotalMinutes(User $user): float
    {
        $totalSeconds = $user->recordings()
            ->where('status', 'completed')
            ->sum('duration_seconds');

        return round($totalSeconds / 60, 1);
    }

    private function getWeekMinutes(User $user, string $week): float
    {
        [$start, $end] = match ($week) {
            'this' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'last' => [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ],
            default => throw new \InvalidArgumentException("Invalid week: {$week}"),
        };

        $seconds = $user->recordings()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('duration_seconds');

        return round($seconds / 60, 1);
    }

    /**
     * Get per-day speaking minutes for the last 7 days.
     *
     * @return Collection<string, float> Keyed by date string (Y-m-d)
     */
    private function getDailyBreakdown(User $user): Collection
    {
        $days = collect();
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        // Raw query for efficiency
        $results = DB::table('recordings')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(duration_seconds) as total_seconds'),
            )
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total_seconds', 'date');

        // Fill in missing days with 0
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[$date] = round(($results[$date] ?? 0) / 60, 1);
        }

        return $days;
    }

    private function getCurrentStreak(User $user): int
    {
        // Get distinct dates with completed recordings, most recent first
        $dates = $user->recordings()
            ->where('status', 'completed')
            ->select(DB::raw('DATE(created_at) as date'))
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d));

        if ($dates->isEmpty()) {
            return 0;
        }

        // Check if spoke today or yesterday (allow same-day grace)
        $today = Carbon::today();
        $mostRecent = $dates->first();

        if ($mostRecent->diffInDays($today) > 1) {
            return 0; // Streak broken
        }

        $streak = 1;
        for ($i = 1; $i < $dates->count(); $i++) {
            $current = $dates[$i];
            $previous = $dates[$i - 1];

            if ($previous->diffInDays($current) === 1) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function getTotalConversations(User $user): int
    {
        return $user->conversations()->count();
    }
}
```

### Step 3 — Create `ProgressData` Value Object

Create `app/Services/Analytics/ProgressData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

final readonly class ProgressData
{
    public function __construct(
        public float $totalMinutes,
        public float $thisWeekMinutes,
        public float $lastWeekMinutes,
        public Collection $dailyBreakdown,
        public int $currentStreak,
        public int $totalConversations,
    ) {}

    public function weekGrowthPercent(): ?float
    {
        if ($this->lastWeekMinutes === 0.0) {
            return null; // Can't calculate growth without last week data
        }

        return round(
            (($this->thisWeekMinutes - $this->lastWeekMinutes) / $this->lastWeekMinutes) * 100,
            1
        );
    }

    public function weekGrowthLabel(): string
    {
        $growth = $this->weekGrowthPercent();

        if ($growth === null) {
            return 'First week! Keep going! 🎉';
        }

        if ($growth > 0) {
            return "+{$growth}% from last week 🔥";
        }

        if ($growth === 0.0) {
            return 'Same as last week';
        }

        return "{$growth}% from last week";
    }

    public function motivationalMessage(): string
    {
        $minutes = $this->totalMinutes;

        return match (true) {
            $minutes === 0.0 => 'Start speaking today, yaar! 🎙️',
            $minutes < 5.0   => 'Great start! Every word counts! ✨',
            $minutes < 30.0  => 'You\'re on a roll! Keep it up! 🚀',
            $minutes < 60.0  => 'Wah! One hour of speaking! Amazing! 🌟',
            $minutes < 120.0 => 'Bilkul fantastic! Two hours done! 🏆',
            default          => 'You\'re a speaking champion, yaar! 👑',
        };
    }

    public function streakMessage(): string
    {
        return match (true) {
            $this->currentStreak === 0 => 'Speak today to start a streak!',
            $this->currentStreak === 1 => '1 day streak — great start!',
            $this->currentStreak < 7  => "{$this->currentStreak} day streak! 🔥",
            $this->currentStreak < 30 => "{$this->currentStreak} day streak! You're unstoppable! 🔥🔥",
            default                   => "{$this->currentStreak} day streak! Legend! 👑",
        };
    }
}
```

### Step 4 — Create the Livewire Component

```bash
php artisan make:livewire Dashboard/ProgressDashboard
```

```php
<?php
// app/Livewire/Dashboard/ProgressDashboard.php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Analytics\ProgressData;
use App\Services\Analytics\UserProgressService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class ProgressDashboard extends Component
{
    public ProgressData $progress;

    public function mount(UserProgressService $progressService): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->progress = $progressService->getProgressData($user);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard.progress-dashboard');
    }
}
```

### Step 5 — Create the Dashboard View

Create `resources/views/livewire/dashboard/progress-dashboard.blade.php`:

```html
<div class="min-h-screen bg-gray-950 px-6 pt-8 pb-24">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Your Progress</h1>
            <p class="text-gray-500 text-sm mt-0.5">
                {{ $progress->motivationalMessage() }}
            </p>
        </div>
        {{-- Streak badge --}}
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-orange-500/10 border border-orange-500/20
                        flex items-center justify-center text-xl">
                🔥
            </div>
            <p class="text-orange-400 text-xs mt-1 font-semibold">
                {{ $progress->currentStreak }}d
            </p>
        </div>
    </div>

    {{-- Hero: Total Minutes --}}
    <div class="bg-gradient-to-br from-orange-500/10 to-rose-500/10 rounded-3xl
                border border-orange-500/20 p-6 mb-6">
        <p class="text-gray-400 text-sm mb-1">Total Time Speaking</p>
        <div class="flex items-end gap-2">
            <span class="text-5xl font-bold text-white">
                {{ number_format($progress->totalMinutes, 1) }}
            </span>
            <span class="text-gray-400 text-lg mb-1.5">mins</span>
        </div>
        <p class="text-orange-400 text-sm mt-2">
            {{ $progress->streakMessage() }}
        </p>
    </div>

    {{-- Weekly Comparison --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 p-4">
            <p class="text-gray-500 text-xs mb-1">This Week</p>
            <p class="text-2xl font-bold text-white">
                {{ $progress->thisWeekMinutes }}
                <span class="text-sm text-gray-500 font-normal">min</span>
            </p>
        </div>
        <div class="bg-gray-900 rounded-2xl border border-gray-800 p-4">
            <p class="text-gray-500 text-xs mb-1">Last Week</p>
            <p class="text-2xl font-bold text-white">
                {{ $progress->lastWeekMinutes }}
                <span class="text-sm text-gray-500 font-normal">min</span>
            </p>
            @if($progress->weekGrowthPercent() !== null)
                <p class="text-xs mt-1 {{ $progress->weekGrowthPercent() >= 0 ? 'text-green-400' : 'text-rose-400' }}">
                    {{ $progress->weekGrowthLabel() }}
                </p>
            @endif
        </div>
    </div>

    {{-- Weekly Growth Label --}}
    <div class="mb-4">
        <p class="text-white font-semibold text-sm">
            {{ $progress->weekGrowthLabel() }}
        </p>
    </div>

    {{-- Daily Bar Chart (last 7 days) --}}
    <div class="bg-gray-900 rounded-2xl border border-gray-800 p-5 mb-6">
        <p class="text-gray-400 text-xs font-medium mb-4 uppercase tracking-wider">
            Last 7 Days
        </p>

        @php
            $maxMinutes = max($progress->dailyBreakdown->max(), 1);
        @endphp

        <div class="flex items-end justify-between gap-2 h-28">
            @foreach ($progress->dailyBreakdown as $date => $minutes)
                @php
                    $heightPercent = ($minutes / $maxMinutes) * 100;
                    $isToday = $date === now()->format('Y-m-d');
                    $dayLabel = \Carbon\Carbon::parse($date)->format('D');
                @endphp

                <div class="flex flex-col items-center gap-1 flex-1">
                    {{-- Bar --}}
                    <div class="w-full flex items-end justify-center"
                         style="height: 96px;">
                        <div
                            class="
                                w-full rounded-t-lg transition-all duration-300
                                {{ $isToday
                                    ? 'bg-gradient-to-t from-orange-500 to-rose-400'
                                    : 'bg-gray-700'
                                }}
                                {{ $minutes > 0 ? 'min-h-1' : 'min-h-0.5 opacity-30' }}
                            "
                            style="height: {{ max($heightPercent, $minutes > 0 ? 4 : 2) }}%;"
                        ></div>
                    </div>
                    {{-- Minutes label (show only if spoken) --}}
                    @if ($minutes > 0)
                        <p class="text-xs text-gray-400">{{ $minutes }}m</p>
                    @else
                        <p class="text-xs text-gray-700">—</p>
                    @endif
                    {{-- Day label --}}
                    <p class="text-xs {{ $isToday ? 'text-orange-400 font-bold' : 'text-gray-600' }}">
                        {{ $isToday ? 'Today' : $dayLabel }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Secondary Stats --}}
    <div class="bg-gray-900 rounded-2xl border border-gray-800 p-5">
        <p class="text-gray-400 text-xs font-medium mb-4 uppercase tracking-wider">
            Milestones
        </p>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-gray-300 text-sm">Total Conversations</p>
                <p class="text-white font-bold">{{ $progress->totalConversations }}</p>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-gray-300 text-sm">Longest Streak</p>
                <p class="text-white font-bold">{{ $progress->currentStreak }} days 🔥</p>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-gray-300 text-sm">Minutes This Week</p>
                <p class="text-white font-bold">{{ $progress->thisWeekMinutes }} min</p>
            </div>
        </div>
    </div>

    {{-- Bottom CTA --}}
    <div class="mt-6 text-center">
        <a href="{{ route('dashboard') }}"
           class="
               inline-flex items-center gap-2 px-8 h-14 rounded-2xl
               bg-gradient-to-r from-orange-500 to-rose-500
               text-white font-semibold text-base shadow-lg shadow-orange-500/25
               active:scale-[0.98] transition-all duration-150
           ">
            🎙️ Start Speaking
        </a>
    </div>
</div>
```

### Step 6 — Add Route

```php
// routes/web.php
use App\Livewire\Dashboard\ProgressDashboard;

Route::middleware(['auth'])->group(function () {
    Route::get('/progress', ProgressDashboard::class)->name('progress');
});
```

### Step 7 — Add Bottom Navigation Bar

Create a shared navigation component `resources/views/components/bottom-nav.blade.php`:

```html
<nav class="fixed bottom-0 left-0 right-0 z-50 bg-gray-950/95 backdrop-blur-sm
            border-t border-gray-800 pb-safe-bottom">
    <div class="flex items-center justify-around px-4 py-3">

        {{-- Speak --}}
        <a href="{{ route('dashboard') }}"
           class="flex flex-col items-center gap-1 flex-1
                  {{ request()->routeIs('dashboard') ? 'text-orange-400' : 'text-gray-600' }}">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M9 5a3 3 0 016 0v6a3 3 0 01-6 0V5z"/>
            </svg>
            <span class="text-xs font-medium">Speak</span>
        </a>

        {{-- Progress --}}
        <a href="{{ route('progress') }}"
           class="flex flex-col items-center gap-1 flex-1
                  {{ request()->routeIs('progress') ? 'text-orange-400' : 'text-gray-600' }}">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <span class="text-xs font-medium">Progress</span>
        </a>

        {{-- Settings --}}
        <a href="{{ route('settings.privacy') }}"
           class="flex flex-col items-center gap-1 flex-1
                  {{ request()->routeIs('settings.*') ? 'text-orange-400' : 'text-gray-600' }}">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-xs font-medium">Settings</span>
        </a>
    </div>
</nav>
```

Include in `resources/views/layouts/app.blade.php`:

```html
<x-bottom-nav />
```

---

## 6. Pest Tests

```php
<?php

use App\Models\Recording;
use App\Models\User;
use App\Services\Analytics\UserProgressService;
use Carbon\Carbon;

describe('UserProgressService', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->service = new UserProgressService();
    });

    it('calculates total minutes from completed recordings', function () {
        Recording::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'duration_seconds' => 60, // 1 minute each
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->totalMinutes)->toBe(3.0);
    });

    it('excludes non-completed recordings', function () {
        Recording::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
            'duration_seconds' => 3600,
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->totalMinutes)->toBe(0.0);
    });

    it('calculates current streak correctly', function () {
        // Spoke 3 consecutive days
        foreach ([0, 1, 2] as $daysAgo) {
            Recording::factory()->create([
                'user_id' => $this->user->id,
                'status' => 'completed',
                'duration_seconds' => 60,
                'created_at' => Carbon::today()->subDays($daysAgo),
            ]);
        }

        $progress = $this->service->getProgressData($this->user);

        expect($progress->currentStreak)->toBe(3);
    });

    it('shows 0 streak if last recording was 2+ days ago', function () {
        Recording::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'duration_seconds' => 60,
            'created_at' => Carbon::today()->subDays(2),
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->currentStreak)->toBe(0);
    });

    it('calculates week growth percentage', function () {
        // Last week: 10 minutes
        Recording::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'duration_seconds' => 600,
            'created_at' => Carbon::now()->subWeek()->startOfWeek()->addDay(),
        ]);

        // This week: 15 minutes
        Recording::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'duration_seconds' => 900,
            'created_at' => Carbon::now()->startOfWeek()->addDay(),
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->weekGrowthPercent())->toBe(50.0);
        expect($progress->weekGrowthLabel())->toContain('+50%');
    });
});
```

---

## 7. Verification Checklist

- [ ] `GET /progress` renders the dashboard for authenticated users
- [ ] Total minutes correct (only counts `status=completed`)
- [ ] This week vs last week comparison displayed
- [ ] Weekly growth percentage calculated and labelled
- [ ] Daily bar chart shows last 7 days with today highlighted
- [ ] Streak counter updates correctly
- [ ] Bottom navigation links to Speak, Progress, Settings
- [ ] Progress data cached for 15 minutes
- [ ] `composer test` passes all analytics tests

---

## 8. Acceptance Criteria

1. Dashboard shows "Total Minutes Spoken" as the hero metric.
2. Weekly growth chart compares this week to last week visually.
3. Current streak counter motivates daily use.
4. All data is from real DB aggregations (no hardcoded values).
5. Progress is cached to avoid N+1 on every page load.
6. Dashboard is accessible via `/progress` and bottom navigation.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| `duration_seconds` is null for most recordings (not set in VOICE-01) | Implement duration estimation from file size as fallback; update NativePHP bridge |
| Chart rendering requires JavaScript library | Use pure CSS bars (as implemented above) for zero-JS dependency |
| Timezone issues (streak calculation) | Use `Carbon::today()` which respects app timezone; ensure `APP_TIMEZONE` is set to user's timezone |
| Cache invalidation after new recording | Invalidate `user_progress_{id}` cache in `RecordingButton::persistRecording()` or via model observer |
| Large user base — aggregate queries slow | Add DB index on `(user_id, status, created_at)` in recordings table |

---

## 10. Database Indexes (Add to Migration)

Update the `recordings` migration or create a new one:

```php
// Add to existing recordings migration or a new one
$table->index(['user_id', 'status', 'created_at'], 'recordings_progress_index');
```

