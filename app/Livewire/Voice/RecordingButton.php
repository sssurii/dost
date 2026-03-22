<?php

declare(strict_types=1);

namespace App\Livewire\Voice;

use App\Events\RecordingFinished;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
class RecordingButton extends Component
{
    /** UI state machine: idle | recording | processing | playing | error */
    public string $uiState = 'idle';

    public ?int $currentRecordingId = null;

    public string $statusMessage = 'Hold to speak';

    public int $userId = 0;

    public function mount(): void
    {
        $this->uiState = 'idle';
        $this->userId = (int) Auth::id();
    }

    /**
     * Called from JavaScript when NativePHP recording starts.
     */
    public function onRecordingStarted(): void
    {
        $this->uiState = 'recording';
        $this->statusMessage = 'Listening...';
    }

    /**
     * Called from JavaScript when NativePHP recording stops.
     * $nativePath is the device-local path returned by the native plugin.
     */
    public function onRecordingStopped(string $nativePath): void
    {
        try {
            $this->uiState = 'processing';
            $this->statusMessage = 'Dost is thinking...';

            $recording = $this->persistRecording($nativePath);
            $this->currentRecordingId = $recording->id;

            RecordingFinished::dispatch($recording);
        } catch (Throwable $e) {
            Log::error('Recording persist failed', [
                'user_id' => Auth::id(),
                'path' => $nativePath,
                'error' => $e->getMessage(),
            ]);

            $this->uiState = 'error';
            $this->statusMessage = 'Something went wrong. Try again!';
        }
    }

    /**
     * Called via Reverb broadcast when AI processing completes (VOICE-02).
     *
     * Payload keys: recording_id, transcript, response_text, audio_url
     *
     * @param  array<string, mixed>  $event
     */
    #[On('echo-private:user.{userId},recording.completed')]
    public function onAiResponseReady(array $event): void
    {
        $this->uiState = 'playing';
        $this->statusMessage = 'Dost is speaking...';

        $this->dispatch('play-ai-response',
            text: $event['response_text'] ?? '',
            audioUrl: $event['audio_url'] ?? null,
        );
    }

    /**
     * Called from JS when AI audio playback finishes.
     */
    public function onPlaybackFinished(): void
    {
        $this->uiState = 'idle';
        $this->statusMessage = 'Hold to speak';
        $this->currentRecordingId = null;
    }

    private function persistRecording(string $nativePath): Recording
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->id;
        $filename = 'rec_'.$userId.'_'.time().'.m4a';
        $storagePath = "recordings/{$userId}/{$filename}";

        Storage::disk('public')->makeDirectory("recordings/{$userId}");

        $fileContents = file_get_contents($nativePath);

        if ($fileContents === false) {
            throw new RuntimeException("Cannot read native audio file: {$nativePath}");
        }

        Storage::disk('public')->put($storagePath, $fileContents);

        $retentionDays = $user->audio_retention_days->value;

        return Recording::create([
            'user_id' => $userId,
            'path' => $storagePath,
            'mime_type' => 'audio/mp4',
            'file_size_bytes' => strlen($fileContents),
            'status' => 'pending',
            'expires_at' => now()->addDays($retentionDays),
        ]);
    }

    public function render(): View
    {
        return view('livewire.voice.recording-button');
    }
}
