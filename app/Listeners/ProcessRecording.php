<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RecordingFinished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class ProcessRecording implements ShouldQueue
{
    use InteractsWithQueue;

    /** Queue AI processing jobs on the dedicated 'ai' queue. */
    public string $queue = 'ai';

    /** Timeout for AI processing (seconds). */
    public int $timeout = 30;

    /**
     * Handle the event.
     * Full implementation in VOICE-02 (TutorAgent integration).
     *
     * {@inheritDoc}
     */
    public function handle(RecordingFinished $event): void
    {
        // VOICE-02: TutorProcessor will be invoked here.
    }
}
