<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\RecordingFinished;
use App\Listeners\ProcessRecording;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event-to-listener mappings for the voice pipeline.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        RecordingFinished::class => [
            ProcessRecording::class,
        ],
    ];
}
