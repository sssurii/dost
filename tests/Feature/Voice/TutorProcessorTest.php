<?php

declare(strict_types=1);

use App\Ai\Agents\TutorAgent;
use App\Events\AiResponseReady;
use App\Events\RecordingFinished;
use App\Listeners\ProcessRecording;
use App\Models\Recording;
use App\Models\User;
use App\Services\Ai\TutorProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('TutorProcessor', function () {

    beforeEach(function () {
        Storage::fake('public');
        $this->user = User::factory()->create();
    });

    it('marks recording completed with transcript and response', function () {
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');

        TutorAgent::fake([
            ['transcript' => 'Hello my name is Raj.', 'response' => 'Wah, great to meet you Raj yaar!'],
        ]);

        $recording = Recording::factory()->for($this->user)->pending()->create([
            'path' => 'recordings/1/test.m4a',
        ]);

        $result = app(TutorProcessor::class)->process($recording);

        expect($result->transcript)->toBe('Hello my name is Raj.')
            ->and($result->response)->toBe('Wah, great to meet you Raj yaar!')
            ->and($recording->fresh()->status->value)->toBe('completed')
            ->and($recording->fresh()->transcript)->toBe('Hello my name is Raj.');

        TutorAgent::assertPrompted(fn ($p) => $p->contains('Transcribe'));
    });

    it('marks recording as failed when Gemini throws', function () {
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');

        TutorAgent::fake(fn () => throw new RuntimeException('API error'));

        $recording = Recording::factory()->for($this->user)->pending()->create([
            'path' => 'recordings/1/test.m4a',
        ]);

        expect(fn () => app(TutorProcessor::class)->process($recording))
            ->toThrow(RuntimeException::class);

        expect($recording->fresh()->status->value)->toBe('failed');
    });
});

describe('ProcessRecording Listener', function () {

    it('dispatches AiResponseReady without queuing TTS on success', function () {
        Storage::fake('public');
        Storage::disk('public')->put('recordings/1/test.m4a', 'fake-audio');
        Event::fake([AiResponseReady::class]);
        Queue::fake();

        TutorAgent::fake([
            ['transcript' => 'Test transcript', 'response' => 'Wah yaar!'],
        ]);

        $recording = Recording::factory()->pending()->create([
            'path' => 'recordings/1/test.m4a',
        ]);

        app(ProcessRecording::class)->handle(new RecordingFinished($recording));

        Event::assertDispatched(AiResponseReady::class);
        Queue::assertNothingPushed();
    });

    it('skips non-pending recordings', function () {
        TutorAgent::fake()->preventStrayPrompts();

        $recording = Recording::factory()->completed()->create();

        app(ProcessRecording::class)->handle(new RecordingFinished($recording));

        TutorAgent::assertNeverPrompted();
    });
});
