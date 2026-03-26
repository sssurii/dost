<?php

declare(strict_types=1);

use App\Livewire\Settings\AudioRetention;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('CleanupAudio command', function () {

    beforeEach(function () {
        Storage::fake('public');
        $this->user = User::factory()->create();
    });

    it('deletes expired audio files and nulls paths while keeping the DB row and text', function () {
        Storage::disk('public')->put('recordings/1/old.m4a', 'audio-data');
        Storage::disk('public')->put('responses/1/response.mp3', 'tts-data');

        $recording = Recording::factory()->completed()->for($this->user)->create([
            'path' => 'recordings/1/old.m4a',
            'ai_response_audio_path' => 'responses/1/response.mp3',
            'transcript' => 'Hello my name is Raj',
            'ai_response_text' => 'Wah! Great to meet you Raj!',
            'duration_seconds' => 12,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        Storage::disk('public')->assertMissing('recordings/1/old.m4a');
        Storage::disk('public')->assertMissing('responses/1/response.mp3');

        $fresh = $recording->fresh();
        expect($fresh)->not->toBeNull()
            ->and($fresh->path)->toBeNull()
            ->and($fresh->ai_response_audio_path)->toBeNull()
            ->and($fresh->transcript)->toBe('Hello my name is Raj')
            ->and($fresh->ai_response_text)->toBe('Wah! Great to meet you Raj!')
            ->and($fresh->duration_seconds)->toBe(12);
    });

    it('does not touch non-expired recordings', function () {
        Storage::disk('public')->put('recordings/1/new.m4a', 'audio-data');

        $recording = Recording::factory()->for($this->user)->create([
            'path' => 'recordings/1/new.m4a',
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        Storage::disk('public')->assertExists('recordings/1/new.m4a');
        expect($recording->fresh()->path)->toBe('recordings/1/new.m4a');
    });

    it('runs dry-run without making any changes', function () {
        Storage::disk('public')->put('recordings/1/old.m4a', 'audio-data');

        $recording = Recording::factory()->for($this->user)->create([
            'path' => 'recordings/1/old.m4a',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists('recordings/1/old.m4a');
        expect($recording->fresh()->path)->toBe('recordings/1/old.m4a');
    });

    it('skips recordings where paths are already null', function () {
        $recording = Recording::factory()->for($this->user)->create([
            'path' => null,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        expect($recording->fresh())->not->toBeNull();
    });

    it('returns null accessor paths after cleanup has removed audio', function () {
        Storage::disk('public')->put('recordings/1/old.m4a', 'audio-data');

        $recording = Recording::factory()->for($this->user)->create([
            'path' => 'recordings/1/old.m4a',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('audio:cleanup')->assertSuccessful();

        $fresh = $recording->fresh();

        expect($fresh)->not->toBeNull()
            ->and($fresh->path)->toBeNull()
            ->and($fresh->full_path)->toBeNull()
            ->and($fresh->public_url)->toBeNull();
    });

    it('limits cleanup to a specific user with --user option', function () {
        $other = User::factory()->create();

        Storage::disk('public')->put('recordings/1/a.m4a', 'data');
        Storage::disk('public')->put('recordings/2/b.m4a', 'data');

        $myRecording = Recording::factory()->for($this->user)->create([
            'path' => 'recordings/1/a.m4a', 'expires_at' => now()->subDay(),
        ]);
        $otherRecording = Recording::factory()->for($other)->create([
            'path' => 'recordings/2/b.m4a', 'expires_at' => now()->subDay(),
        ]);

        $this->artisan("audio:cleanup --user={$this->user->id}")->assertSuccessful();

        Storage::disk('public')->assertMissing('recordings/1/a.m4a');
        Storage::disk('public')->assertExists('recordings/2/b.m4a');
        expect($myRecording->fresh()->path)->toBeNull()
            ->and($otherRecording->fresh()->path)->not->toBeNull();
    });
});

describe('AudioRetention settings', function () {

    it('loads the current retention setting on mount', function () {
        $user = User::factory()->create(['audio_retention_days' => 7]);

        Livewire::actingAs($user)
            ->test(AudioRetention::class)
            ->assertSet('retentionDays', 7);
    });

    it('saves the new retention preference', function () {
        $user = User::factory()->create(['audio_retention_days' => 2]);

        Livewire::actingAs($user)
            ->test(AudioRetention::class)
            ->set('retentionDays', 1)
            ->call('save')
            ->assertDispatched('saved');

        expect($user->fresh()->audio_retention_days->value)->toBe(1);
    });

    it('updates expires_at on active recordings when preference changes', function () {
        $user = User::factory()->create(['audio_retention_days' => 2]);

        $recording = Recording::factory()->completed()->for($user)->create([
            'created_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($user)
            ->test(AudioRetention::class)
            ->set('retentionDays', 7)
            ->call('save');

        expect($recording->fresh()->expires_at->equalTo($recording->created_at->copy()->addDays(7)))->toBeTrue();
    });

    it('recomputes expires_at from created_at when retention is shortened', function () {
        $user = User::factory()->create(['audio_retention_days' => 7]);

        $recording = Recording::factory()->completed()->for($user)->create([
            'created_at' => now()->subDays(6),
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($user)
            ->test(AudioRetention::class)
            ->set('retentionDays', 1)
            ->call('save');

        expect($recording->fresh()->expires_at->equalTo($recording->created_at->copy()->addDay()))->toBeTrue()
            ->and($recording->fresh()->expires_at->isPast())->toBeTrue();
    });

    it('rejects invalid retention values', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(AudioRetention::class)
            ->set('retentionDays', 5)
            ->call('save')
            ->assertHasErrors(['retentionDays']);
    });
});
