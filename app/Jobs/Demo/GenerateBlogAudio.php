<?php

declare(strict_types=1);

namespace App\Jobs\Demo;

use App\Events\BlogStudioResultReady;
use App\Models\BlogPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;
use Laravel\Ai\Responses\AudioResponse;

final class GenerateBlogAudio implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /** @var string[] */
    private const PROVIDERS = ['demo-broken', 'openai'];

    public function __construct(
        public string $blogPostId,
        public string $articleContent,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        /** @var array<int, array{provider: string, status: string, message: string}> $failoverLog */
        $failoverLog = [];
        $response = null;

        foreach (self::PROVIDERS as $provider) {
            try {
                /** @var AudioResponse $response */
                $response = Audio::of($this->articleContent)
                    ->voice('nova')
                    ->generate([$provider => 'tts-1']);

                $failoverLog[] = [
                    'provider' => ucfirst($provider),
                    'status' => 'success',
                    'message' => 'Audio generated successfully.',
                ];

                break;
            } catch (\Throwable $e) {
                $failoverLog[] = [
                    'provider' => ucfirst($provider),
                    'status' => 'failed',
                    'message' => Str::limit($e->getMessage(), 120),
                ];
            }
        }

        if (! $response instanceof AudioResponse) {
            $failoverLog[] = [
                'provider' => 'All',
                'status' => 'failed',
                'message' => 'All audio providers failed.',
            ];

            BlogPost::where('id', $this->blogPostId)->update([
                'audio_failover_log' => $failoverLog,
            ]);

            BlogStudioResultReady::dispatch($this->blogPostId, 'audio', [
                'url' => '',
            ], $failoverLog);

            return;
        }

        $path = (string) $response->storePublicly('demo/audio', 'public');

        BlogPost::where('id', $this->blogPostId)->update([
            'audio_path' => $path,
            'audio_failover_log' => $failoverLog,
        ]);

        BlogStudioResultReady::dispatch($this->blogPostId, 'audio', [
            'url' => Storage::disk('public')->url($path),
        ], $failoverLog);
    }
}
