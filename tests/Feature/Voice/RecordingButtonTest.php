<?php

declare(strict_types=1);

use App\Enums\RecordingStatus;
use App\Events\RecordingFinished;
use App\Livewire\Voice\RecordingButton;
use App\Models\Recording;
use App\Models\User;
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
});
