<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\RecordingStatus;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UserProgressService
{
    private const int CACHE_TTL = 900;

    public function getProgressData(User $user): ProgressData
    {
        return Cache::remember("user_progress_{$user->id}", self::CACHE_TTL, function () use ($user) {
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
        $seconds = $user->recordings()->where('status', RecordingStatus::Completed)->sum('duration_seconds');

        return round((float) $seconds / 60, 1);
    }

    private function getWeekMinutes(User $user, string $week): float
    {
        [$start, $end] = match ($week) {
            'this' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'last' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            default => throw new InvalidArgumentException("Invalid week: {$week}"),
        };

        $seconds = $user->recordings()
            ->where('status', RecordingStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->sum('duration_seconds');

        return round((float) $seconds / 60, 1);
    }

    /**
     * Per-day speaking minutes for the last 7 days.
     *
     * @return Collection<string, float>
     */
    private function getDailyBreakdown(User $user): Collection
    {
        $results = Recording::query()
            ->selectRaw('DATE(created_at) as date, SUM(duration_seconds) as total_seconds')
            ->where('user_id', $user->id)
            ->where('status', RecordingStatus::Completed)
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->pluck('total_seconds', 'date');

        /** @var Collection<string, float> $days */
        $days = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[$date] = round((float) ($results[$date] ?? 0) / 60, 1);
        }

        return $days;
    }

    private function getCurrentStreak(User $user): int
    {
        /** @var Collection<int, Carbon> $dates */
        $dates = $user->recordings()
            ->where('status', RecordingStatus::Completed)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn (string $d): Carbon => Carbon::parse($d));

        if ($dates->isEmpty() || (int) $dates->first()->diffInDays(Carbon::today()) > 1) {
            return 0;
        }

        $streak = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            if (! $dates[$i - 1]->copy()->subDay()->isSameDay($dates[$i])) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function getTotalConversations(User $user): int
    {
        return (int) DB::table('agent_conversations')->where('user_id', $user->id)->count();
    }
}
