<?php

declare(strict_types=1);

use App\Livewire\Voice\RecordingButton;
use App\Models\Recording;
use App\Models\User;
use Livewire\Livewire;

describe('VOICE-03: TTS Playback', function () {

    it('transitions to playing state and dispatches play-ai-response on Tier 1 path', function () {
        $user = User::factory()->create();
        $recording = Recording::factory()->completed()->for($user)->create([
            'ai_response_text' => 'Wah! Great to meet you Raj yaar!',
        ]);

        Livewire::actingAs($user)
            ->test(RecordingButton::class)
            ->call('onAiResponseReady', [
                'recording_id' => $recording->id,
                'transcript' => 'Hello my name is Raj.',
                'response_text' => 'Wah! Great to meet you Raj yaar!',
                'audio_url' => null,
            ])
            ->assertSet('uiState', 'playing')
            ->assertSet('statusMessage', 'Dost is speaking...')
            ->assertDispatched('play-ai-response', text: 'Wah! Great to meet you Raj yaar!', audioUrl: null);
    });

    it('transitions to playing state and passes audioUrl on Tier 2 path', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(RecordingButton::class)
            ->call('onAiResponseReady', [
                'recording_id' => 1,
                'transcript' => 'Test.',
                'response_text' => 'Wah yaar!',
                'audio_url' => 'https://example.com/responses/1/response.mp3',
            ])
            ->assertSet('uiState', 'playing')
            ->assertDispatched('play-ai-response',
                text: 'Wah yaar!',
                audioUrl: 'https://example.com/responses/1/response.mp3',
            );
    });

    it('returns to idle after onPlaybackFinished is called', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->call('onPlaybackFinished')
            ->assertSet('uiState', 'idle')
            ->assertSet('statusMessage', 'Hold to speak');
    });

    it('clears currentRecordingId after playback finishes', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->set('currentRecordingId', 42)
            ->call('onPlaybackFinished')
            ->assertSet('currentRecordingId', null);
    });

    it('mic button shows disabled state while playing', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'playing')
            ->assertSee('cursor-not-allowed');
    });

    it('mic button is not disabled in idle state', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->set('uiState', 'idle')
            ->assertDontSee('cursor-not-allowed');
    });

    it('handles missing response_text gracefully by dispatching empty string', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(RecordingButton::class)
            ->call('onAiResponseReady', [
                'recording_id' => 1,
            ])
            ->assertSet('uiState', 'playing')
            ->assertDispatched('play-ai-response', text: '', audioUrl: null);
    });
});
