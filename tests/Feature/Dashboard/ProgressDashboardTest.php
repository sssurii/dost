<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ProgressDashboard;
use App\Models\Recording;
use App\Models\User;
use App\Services\Analytics\UserProgressService;
use Carbon\Carbon;
use Livewire\Livewire;

describe('UserProgressService', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->service = new UserProgressService;
    });

    it('returns zero minutes for a user with no recordings', function () {
        $progress = $this->service->getProgressData($this->user);

        expect($progress->totalMinutes)->toBe(0.0)
            ->and($progress->currentStreak)->toBe(0)
            ->and($progress->totalConversations)->toBe(0);
    });

    it('calculates total minutes from completed recordings only', function () {
        Recording::factory()->count(3)->for($this->user)->create([
            'status' => 'completed',
            'duration_seconds' => 60,
        ]);
        Recording::factory()->for($this->user)->create([
            'status' => 'failed',
            'duration_seconds' => 3600,
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->totalMinutes)->toBe(3.0);
    });

    it('calculates current streak for consecutive days', function () {
        foreach ([0, 1, 2] as $daysAgo) {
            Recording::factory()->for($this->user)->create([
                'status' => 'completed',
                'duration_seconds' => 60,
                'created_at' => Carbon::today()->subDays($daysAgo),
            ]);
        }

        $progress = $this->service->getProgressData($this->user);

        expect($progress->currentStreak)->toBe(3);
    });

    it('resets streak to zero if last recording was 2+ days ago', function () {
        Recording::factory()->for($this->user)->create([
            'status' => 'completed',
            'duration_seconds' => 60,
            'created_at' => Carbon::today()->subDays(2),
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->currentStreak)->toBe(0);
    });

    it('calculates week growth percentage', function () {
        Recording::factory()->for($this->user)->create([
            'status' => 'completed',
            'duration_seconds' => 600,
            'created_at' => Carbon::now()->subWeek()->startOfWeek()->addDay(),
        ]);
        Recording::factory()->for($this->user)->create([
            'status' => 'completed',
            'duration_seconds' => 900,
            'created_at' => Carbon::now()->startOfWeek()->addDay(),
        ]);

        $progress = $this->service->getProgressData($this->user);

        expect($progress->weekGrowthPercent())->toBe(50.0)
            ->and($progress->weekGrowthLabel())->toContain('+50%');
    });

    it('returns null growth when there is no last-week data', function () {
        $progress = $this->service->getProgressData($this->user);

        expect($progress->weekGrowthPercent())->toBeNull()
            ->and($progress->weekGrowthLabel())->toContain('First week');
    });

    it('returns 7 daily entries in the breakdown', function () {
        $progress = $this->service->getProgressData($this->user);

        expect($progress->dailyBreakdown)->toHaveCount(7);
    });

    it('invalidates cache so fresh data is returned after a new recording', function () {
        $progress1 = $this->service->getProgressData($this->user);
        expect($progress1->totalMinutes)->toBe(0.0);

        Recording::factory()->for($this->user)->create([
            'status' => 'completed', 'duration_seconds' => 120,
        ]);

        $this->service->invalidateCache($this->user);

        $progress2 = $this->service->getProgressData($this->user);
        expect($progress2->totalMinutes)->toBe(2.0);
    });
});

describe('ProgressDashboard component', function () {

    it('renders the progress page for authenticated users', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(ProgressDashboard::class)
            ->assertOk()
            ->assertSee('Your Progress');
    });

    it('shows total minutes for the user', function () {
        $user = User::factory()->create();
        Recording::factory()->for($user)->create([
            'status' => 'completed', 'duration_seconds' => 120,
        ]);

        Livewire::actingAs($user)
            ->test(ProgressDashboard::class)
            ->assertSee('2.0');
    });

    it('progress route is accessible to authenticated users', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('progress'))
            ->assertOk();
    });

    it('progress route redirects guests', function () {
        $this->get(route('progress'))
            ->assertRedirect(route('login'));
    });
});
