<?php

declare(strict_types=1);

namespace App\Livewire\Voice;

use App\Events\RecordingFinished;
use App\Models\Recording;
use App\Models\User;
use App\Services\Analytics\UserProgressService;
use App\Support\NativePhpEnvironment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
final class RecordingButton extends Component
{
    use WithFileUploads;

    /** UI state machine: idle | recording | processing | playing | error */
    public string $uiState = 'idle';

    public ?int $currentRecordingId = null;

    public string $statusMessage = 'Hold to speak';

    public int $userId = 0;

    /**
     * Holds the browser-uploaded audio file until onBrowserAudioUploaded processes it.
     *
     * @var TemporaryUploadedFile|null
     */
    public $audioFile = null;

    /**
     * Timestamped log entries shown in the debug overlay (debug mode only).
     *
     * @var array<int, array{time: string, context: string, message: string}>
     */
    public array $debugLogs = [];

    /** True when served inside NativePHP (mobile jump / installed APK). */
    public bool $isNativePHP = false;

    public function mount(): void
    {
        $this->uiState = 'idle';
        $this->userId = (int) Auth::id();
        $this->isNativePHP = NativePhpEnvironment::isRunningInsideNativePhp($_SERVER);
    }

    /**
     * Called from JS after 45 s with no AI response — resets the UI so the
     * mic is usable again even when Reverb is down or the job silently failed.
     */
    public function onProcessingTimeout(): void
    {
        if ($this->uiState !== 'processing') {
            return;
        }

        Log::warning('Processing timeout: no AI response received within 45 s', [
            'user_id' => Auth::id(),
            'recording_id' => $this->currentRecordingId,
        ]);

        $this->uiState = 'error';
        $this->statusMessage = 'AI is taking too long. Please try again!';
        $this->appendDebugLog('timeout', 'No broadcast received after 45 s');
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
        $this->handleRecordedAudio($nativePath);
    }

    #[On('native:Native\Mobile\Events\Microphone\MicrophoneRecorded')]
    public function onMicrophoneRecorded(string $path, string $mimeType = 'audio/m4a'): void
    {
        $this->handleRecordedAudio($path, $mimeType);
    }

    #[On('native:Native\Mobile\Events\Microphone\MicrophoneCancelled')]
    public function onMicrophoneCancelled(): void
    {
        $this->uiState = 'idle';
        $this->statusMessage = 'Hold to speak';
    }

    /**
     * Called from JavaScript after $wire.upload('audioFile') completes.
     * The file is available as $this->audioFile (TemporaryUploadedFile).
     */
    public function onBrowserAudioUploaded(string $mimeType): void
    {
        try {
            $this->uiState = 'processing';
            $this->statusMessage = 'Dost is thinking...';

            $recording = $this->persistRecordingFromUpload($mimeType);
            $this->currentRecordingId = $recording->id;

            RecordingFinished::dispatch($recording);
        } catch (Throwable $e) {
            Log::error('Browser recording persist failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            $this->uiState = 'error';
            $this->statusMessage = config('app.debug')
                ? 'PHP: '.$e->getMessage()
                : 'Something went wrong. Try again!';

            $this->appendDebugLog('PHP:persistRecordingFromUpload', $e->getMessage());
        }
    }

    private function handleRecordedAudio(string $nativePath, string $mimeType = 'audio/m4a'): void
    {
        try {
            $this->uiState = 'processing';
            $this->statusMessage = 'Dost is thinking...';

            $recording = $this->persistRecording($nativePath, $mimeType);
            $this->currentRecordingId = $recording->id;

            RecordingFinished::dispatch($recording);
        } catch (Throwable $e) {
            Log::error('Recording persist failed', [
                'user_id' => Auth::id(),
                'path' => $nativePath,
                'error' => $e->getMessage(),
            ]);

            $this->uiState = 'error';
            $this->statusMessage = config('app.debug')
                ? 'PHP: '.$e->getMessage()
                : 'Something went wrong. Try again!';

            $this->appendDebugLog('PHP:persistRecording', $e->getMessage());
        }
    }

    /**
     * Called from JavaScript to forward a client-side error to server logs and the debug overlay.
     */
    public function logJsError(string $context, string $message): void
    {
        Log::error("RecordingButton JS [{$context}]", [
            'user_id' => Auth::id(),
            'message' => $message,
        ]);

        $this->appendDebugLog($context, $message);
    }

    /**
     * Received via Reverb when the AI queue job permanently fails.
     *
     * @param  array<string, mixed>  $event
     */
    #[On('echo-private:user.{userId},recording.failed')]
    public function onRecordingFailed(array $event): void
    {
        $this->uiState = 'error';

        $this->statusMessage = (config('app.debug') && isset($event['error_message']))
            ? 'AI failed: '.$event['error_message']
            : 'AI processing failed. Try again!';

        $this->appendDebugLog('ProcessRecording:failed', $event['error_message'] ?? 'no detail');
    }

    /**
     * Append an entry to the debug log (no-op in production).
     */
    private function appendDebugLog(string $context, string $message): void
    {
        if (! config('app.debug')) {
            return;
        }

        $this->debugLogs[] = [
            'time' => now()->format('H:i:s'),
            'context' => $context,
            'message' => $message,
        ];

        if (count($this->debugLogs) > 30) {
            $this->debugLogs = array_slice($this->debugLogs, -30);
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

    private function persistRecording(string $nativePath, string $mimeType = 'audio/m4a'): Recording
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

        $recording = Recording::create([
            'user_id' => $userId,
            'path' => $storagePath,
            'mime_type' => $mimeType === 'audio/m4a' ? 'audio/mp4' : $mimeType,
            'duration_seconds' => $this->estimateDuration($fileContents),
            'file_size_bytes' => strlen($fileContents),
            'status' => 'pending',
            'expires_at' => now()->addDays($retentionDays),
        ]);

        app(UserProgressService::class)->invalidateCache($user);

        return $recording;
    }

    /**
     * Estimate audio duration from file size.
     * Rough heuristic: 128 kbps AAC ≈ 16 KB/s.
     */
    private function estimateDuration(string $fileContents): int
    {
        return (int) max(1, round(strlen($fileContents) / (16 * 1024)));
    }

    /**
     * Persist a browser-uploaded audio file (via Livewire WithFileUploads) to
     * public storage and create the Recording model.
     */
    private function persistRecordingFromUpload(string $mimeType): Recording
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->audioFile instanceof TemporaryUploadedFile) {
            throw new RuntimeException('No audio file uploaded.');
        }

        $userId = $user->id;
        $extension = match (true) {
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'wav') => 'wav',
            str_contains($mimeType, 'mp4') || str_contains($mimeType, 'm4a') => 'mp4',
            default => 'webm',
        };

        $filename = 'rec_'.$userId.'_'.time().'.'.$extension;
        $storagePath = "recordings/{$userId}/{$filename}";

        Storage::disk('public')->makeDirectory("recordings/{$userId}");

        $fileContents = $this->audioFile->get();
        Storage::disk('public')->put($storagePath, $fileContents);

        $retentionDays = $user->audio_retention_days->value;

        $recording = Recording::create([
            'user_id' => $userId,
            'path' => $storagePath,
            'mime_type' => $mimeType,
            'duration_seconds' => $this->estimateDuration($fileContents),
            'file_size_bytes' => $this->audioFile->getSize(),
            'status' => 'pending',
            'expires_at' => now()->addDays($retentionDays),
        ]);

        $this->audioFile = null;

        app(UserProgressService::class)->invalidateCache($user);

        return $recording;
    }

    public function render(): View
    {
        return view('livewire.voice.recording-button');
    }
}
