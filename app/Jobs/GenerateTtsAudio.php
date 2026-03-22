<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Recording;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateTtsAudio implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Recording $recording,
    ) {
        $this->onQueue('tts');
    }

    /**
     * Full implementation in VOICE-03.
     */
    public function handle(): void
    {
        // VOICE-03: generate TTS audio and update $this->recording->ai_response_audio_path
    }
}
