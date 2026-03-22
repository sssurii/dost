<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AiResponseReady;
use App\Events\RecordingFinished;
use App\Jobs\GenerateTtsAudio;
use App\Services\Ai\TutorProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class ProcessRecording implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'ai';

    public int $tries = 2;

    public int $timeout = 30;

    public int $backoff = 5;

    public function __construct(
        private readonly TutorProcessor $processor,
    ) {}

    public function handle(RecordingFinished $event): void
    {
        $recording = $event->recording;
        if (! $recording->isPending()) {
            return;
        }
        $result = $this->processor->process($recording);
        AiResponseReady::dispatch($result->recording);
        GenerateTtsAudio::dispatch($result->recording);
    }

    public function failed(RecordingFinished $event, \Throwable $exception): void
    {
        $event->recording->markAsFailed();
        Log::error('ProcessRecording permanently failed', [
            'recording_id' => $event->recording->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
