<?php

declare(strict_types=1);

use App\Events\AiResponseReady;
use App\Listeners\InvalidateProgressCache;
use App\Models\Recording;
use App\Models\User;
use App\Providers\EventServiceProvider;
use App\Services\Analytics\UserProgressService;
use Illuminate\Support\Facades\Cache;

describe('InvalidateProgressCache listener', function () {

    it('is registered as a listener for AiResponseReady', function () {
        $provider = app()->resolveProvider(EventServiceProvider::class);
        $listen = (new ReflectionProperty($provider, 'listen'))->getValue($provider);

        expect($listen[AiResponseReady::class] ?? [])
            ->toContain(InvalidateProgressCache::class);
    });

    it('invalidates the user progress cache when AiResponseReady fires', function () {
        $user = User::factory()->create();
        $recording = Recording::factory()->completed()->for($user)->create();
        $service = app(UserProgressService::class);

        $service->getProgressData($user);
        expect(Cache::has("user_progress_{$user->id}"))->toBeTrue();

        event(new AiResponseReady($recording));

        expect(Cache::has("user_progress_{$user->id}"))->toBeFalse();
    });

    it('returns fresh progress data after AiResponseReady fires', function () {
        $user = User::factory()->create();
        $service = app(UserProgressService::class);

        $before = $service->getProgressData($user);
        expect($before->totalMinutes)->toBe(0.0);

        $recording = Recording::factory()->completed()->for($user)->create([
            'duration_seconds' => 180,
        ]);

        event(new AiResponseReady($recording));

        $after = $service->getProgressData($user);
        expect($after->totalMinutes)->toBe(3.0);
    });
});
