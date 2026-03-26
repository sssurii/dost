<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

final readonly class ProgressData
{
    /**
     * @param  Collection<string, float>  $dailyBreakdown  Keyed by Y-m-d date string
     */
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
            return null;
        }

        return round(
            (($this->thisWeekMinutes - $this->lastWeekMinutes) / $this->lastWeekMinutes) * 100,
            1,
        );
    }

    public function weekGrowthLabel(): string
    {
        $growth = $this->weekGrowthPercent();

        if ($growth === null) {
            return 'First week! Keep going! 🎉';
        }

        if ($growth > 0.0) {
            return "+{$growth}% from last week 🔥";
        }

        if ($growth < 0.0) {
            return "{$growth}% from last week";
        }

        return 'Same as last week';
    }

    public function motivationalMessage(): string
    {
        $minutes = $this->totalMinutes;

        return match (true) {
            $minutes === 0.0 => 'Start speaking today, yaar! 🎙️',
            $minutes < 5.0 => 'Great start! Every word counts! ✨',
            $minutes < 30.0 => "You're on a roll! Keep it up! 🚀",
            $minutes < 60.0 => 'Wah! One hour of speaking! Amazing! 🌟',
            $minutes < 120.0 => 'Bilkul fantastic! Two hours done! 🏆',
            default => "You're a speaking champion, yaar! 👑",
        };
    }

    public function streakMessage(): string
    {
        return match (true) {
            $this->currentStreak === 0 => 'Speak today to start a streak!',
            $this->currentStreak === 1 => '1 day streak — great start!',
            $this->currentStreak < 7 => "{$this->currentStreak} day streak! 🔥",
            $this->currentStreak < 30 => "{$this->currentStreak} day streak! You're unstoppable! 🔥🔥",
            default => "{$this->currentStreak} day streak! Legend! 👑",
        };
    }
}
