<?php

declare(strict_types=1);

use App\Enums\RecordingStatus;
use App\Events\RecordingFailed;
use App\Events\RecordingFinished;
use App\Listeners\ProcessRecording;
use App\Livewire\Voice\RecordingButton;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('RecordingButton', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        Storage::fake('public');
        Event::fake([RecordingFinished::class]);
    });

    it('renders in idle state for authenticated user', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->assertSet('uiState', 'idle')
            ->assertSet('statusMessage', 'Hold to speak')
            ->assertSeeHtml('id="mic-button"');
    });

    it('transitions to recording state when started', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStarted')
            ->assertSet('uiState', 'recording')
            ->assertSet('statusMessage', 'Listening...');
    });

    it('transitions to processing and dispatches event when stopped', function () {
        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStarted')
            ->call('onRecordingStopped', $fakePath)
            ->assertSet('uiState', 'processing')
            ->assertSet('statusMessage', 'Dost is thinking...');

        Event::assertDispatched(RecordingFinished::class);
    });

    it('transitions to processing and dispatches event when a native microphone recording completes', function () {
        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStarted')
            ->call('onMicrophoneRecorded', $fakePath, 'audio/m4a')
            ->assertSet('uiState', 'processing')
            ->assertSet('statusMessage', 'Dost is thinking...');

        Event::assertDispatched(RecordingFinished::class);
    });

    it('persists recording with correct user, status, and expiry', function () {
        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStopped', $fakePath);

        $this->assertDatabaseHas('recordings', [
            'user_id' => $this->user->id,
            'mime_type' => 'audio/mp4',
            'status' => RecordingStatus::Pending->value,
        ]);

        $recording = Recording::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        expect($recording)->not->toBeNull()
            ->and($recording->expires_at)->not->toBeNull()
            ->and($recording->expires_at->isFuture())->toBeTrue();
    });

    it('stores audio file in the correct storage path', function () {
        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStopped', $fakePath);

        $recording = Recording::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        expect($recording)->not->toBeNull();
        Storage::disk('public')->assertExists($recording->path);
        expect($recording->path)->toStartWith("recordings/{$this->user->id}/");
    });

    it('transitions to idle after playback finishes', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->set('currentRecordingId', 99)
            ->call('onPlaybackFinished')
            ->assertSet('uiState', 'idle')
            ->assertSet('statusMessage', 'Hold to speak')
            ->assertSet('currentRecordingId', null);
    });

    it('returns to idle when the native microphone recording is cancelled', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->set('uiState', 'recording')
            ->set('statusMessage', 'Listening...')
            ->call('onMicrophoneCancelled')
            ->assertSet('uiState', 'idle')
            ->assertSet('statusMessage', 'Hold to speak');
    });

    it('transitions to error state when recording file cannot be read', function () {
        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStopped', '/nonexistent/path/fake.m4a')
            ->assertSet('uiState', 'error');
    });

    it('uses user retention days for expires_at', function () {
        $this->user->update(['audio_retention_days' => 7]);

        Storage::disk('public')->put('test.m4a', 'fake-audio-data');
        $fakePath = Storage::disk('public')->path('test.m4a');

        Livewire::actingAs($this->user)
            ->test(RecordingButton::class)
            ->call('onRecordingStopped', $fakePath);

        $recording = Recording::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        expect($recording)->not->toBeNull()
            ->and(now()->diffInDays($recording->expires_at))->toBeGreaterThanOrEqual(6);
    });

    it('redirects guest to login', function () {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    });

    describe('debug logging', function () {

        it('logJsError adds an entry to debugLogs when app.debug is true', function () {
            Config::set('app.debug', true);

            $component = Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->call('logJsError', 'Microphone.Start', 'Bridge call timed out');

            expect($component->get('debugLogs'))->toHaveCount(1)
                ->and($component->get('debugLogs.0.context'))->toBe('Microphone.Start')
                ->and($component->get('debugLogs.0.message'))->toBe('Bridge call timed out');
        });

        it('logJsError does not add entries when app.debug is false', function () {
            Config::set('app.debug', false);

            $component = Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->call('logJsError', 'Microphone.Start', 'Bridge call timed out');

            expect($component->get('debugLogs'))->toBeEmpty();
        });

        it('shows the actual PHP error message in statusMessage when app.debug is true', function () {
            Config::set('app.debug', true);

            $component = Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->call('onRecordingStopped', '/nonexistent/path/fake.m4a');

            expect($component->get('uiState'))->toBe('error')
                ->and($component->get('statusMessage'))->toStartWith('PHP:');
        });

        it('shows generic error message in statusMessage when app.debug is false', function () {
            Config::set('app.debug', false);

            $component = Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->call('onRecordingStopped', '/nonexistent/path/fake.m4a');

            expect($component->get('uiState'))->toBe('error')
                ->and($component->get('statusMessage'))->toBe('Something went wrong. Try again!');
        });

        it('onProcessingTimeout resets processing state to error', function () {
            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('uiState', 'processing')
                ->set('currentRecordingId', 99)
                ->call('onProcessingTimeout')
                ->assertSet('uiState', 'error')
                ->assertSet('statusMessage', 'AI is taking too long. Please try again!');
        });

        it('onProcessingTimeout is a no-op when not in processing state', function () {
            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('uiState', 'idle')
                ->call('onProcessingTimeout')
                ->assertSet('uiState', 'idle');
        });

    });

    describe('RecordingFailed broadcast', function () {

        it('transitions to error state when the recording.failed broadcast is received', function () {
            $recording = Recording::factory()->for($this->user)->create();

            $component = Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('uiState', 'processing');

            $component->call('onRecordingFailed', ['recording_id' => $recording->id]);

            expect($component->get('uiState'))->toBe('error')
                ->and($component->get('statusMessage'))->toBe('AI processing failed. Try again!');
        });

        it('shows AI error detail in statusMessage when app.debug is true', function () {
            Config::set('app.debug', true);

            $recording = Recording::factory()->for($this->user)->create();

            $component = Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('uiState', 'processing');

            $component->call('onRecordingFailed', [
                'recording_id' => $recording->id,
                'error_message' => 'Gemini quota exceeded',
            ]);

            expect($component->get('uiState'))->toBe('error')
                ->and($component->get('statusMessage'))->toBe('AI failed: Gemini quota exceeded');
        });

        it('dispatches RecordingFailed broadcast when ProcessRecording permanently fails', function () {
            Event::fake([RecordingFailed::class]);

            $recording = Recording::factory()->for($this->user)->create(['status' => RecordingStatus::Pending]);
            $event = new RecordingFinished($recording);
            $exception = new RuntimeException('Gemini quota exceeded');

            $listener = app(ProcessRecording::class);
            $listener->failed($event, $exception);

            Event::assertDispatched(RecordingFailed::class, function (RecordingFailed $e) use ($recording) {
                return $e->recording->id === $recording->id
                    && $e->errorMessage === 'Gemini quota exceeded';
            });
        });

        it('marks recording as failed when ProcessRecording permanently fails', function () {
            Event::fake([RecordingFailed::class]);

            $recording = Recording::factory()->for($this->user)->create(['status' => RecordingStatus::Pending]);
            $event = new RecordingFinished($recording);

            $listener = app(ProcessRecording::class);
            $listener->failed($event, new RuntimeException('Network error'));

            expect($recording->fresh()->status)->toBe(RecordingStatus::Failed);
        });

        it('broadcastWith omits error_message when app.debug is false', function () {
            Config::set('app.debug', false);

            $recording = Recording::factory()->for($this->user)->create();
            $event = new RecordingFailed($recording, 'sensitive error details');

            expect($event->broadcastWith())->toHaveKey('recording_id')
                ->and($event->broadcastWith())->not->toHaveKey('error_message');
        });

        it('broadcastWith includes error_message when app.debug is true', function () {
            Config::set('app.debug', true);

            $recording = Recording::factory()->for($this->user)->create();
            $event = new RecordingFailed($recording, 'sensitive error details');

            expect($event->broadcastWith())->toHaveKey('error_message', 'sensitive error details');
        });

    });

    describe('browser recording', function () {

        it('onBrowserAudioUploaded creates a Recording and dispatches RecordingFinished', function () {
            Event::fake([RecordingFinished::class]);

            $file = UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');

            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('audioFile', $file)
                ->call('onBrowserAudioUploaded', 'audio/webm')
                ->assertSet('uiState', 'processing')
                ->assertSet('statusMessage', 'Dost is thinking...');

            Event::assertDispatched(RecordingFinished::class);

            $this->assertDatabaseHas('recordings', [
                'user_id' => $this->user->id,
                'mime_type' => 'audio/webm',
                'status' => RecordingStatus::Pending->value,
            ]);
        });

        it('onBrowserAudioUploaded stores the file at the correct path', function () {
            Event::fake([RecordingFinished::class]);

            $file = UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');

            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('audioFile', $file)
                ->call('onBrowserAudioUploaded', 'audio/webm');

            $recording = Recording::query()->where('user_id', $this->user->id)->latest()->first();
            expect($recording)->not->toBeNull();
            Storage::disk('public')->assertExists($recording->path);
            expect($recording->path)->toStartWith("recordings/{$this->user->id}/");
        });

        it('onBrowserAudioUploaded transitions to error when no file is set', function () {
            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->call('onBrowserAudioUploaded', 'audio/webm')
                ->assertSet('uiState', 'error');
        });

        it('onBrowserAudioUploaded uses mp4 extension for mp4 mime type', function () {
            Event::fake([RecordingFinished::class]);

            $file = UploadedFile::fake()->create('recording.mp4', 50, 'audio/mp4');

            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->set('audioFile', $file)
                ->call('onBrowserAudioUploaded', 'audio/mp4');

            $recording = Recording::query()->where('user_id', $this->user->id)->latest()->first();
            expect($recording)->not->toBeNull()
                ->and($recording->path)->toEndWith('.mp4');
        });

        it('isNativePHP is false when not running inside NativePHP', function () {
            Livewire::actingAs($this->user)
                ->test(RecordingButton::class)
                ->assertSet('isNativePHP', false);
        });

    });
});
