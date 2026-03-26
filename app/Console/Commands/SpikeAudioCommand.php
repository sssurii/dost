<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Filesystem\Filesystem;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Files\LocalAudio;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

/**
 * RND-01: Gemini Audio Compatibility Spike
 *
 * Tests whether Gemini 2.5 Flash accepts .m4a audio directly via the Laravel AI SDK,
 * and whether explicit MIME overrides affect acceptance.
 *
 * Run: ./bin/artisan spike:audio
 */
final class SpikeAudioCommand extends Command
{
    protected $signature = 'spike:audio';

    protected $description = 'RND-01: Test M4A audio compatibility with Gemini 2.5 Flash via laravel/ai';

    public function handle(): int
    {
        $sourcePath = storage_path('app/audio-spike/test.m4a');

        $this->printHeader($sourcePath);

        if (! file_exists($sourcePath)) {
            $this->error("Test audio not found at: {$sourcePath}");
            $this->line('Generate it with: ffmpeg -f lavfi -i "sine=frequency=440:duration=5" -c:a aac -b:a 128k '.$sourcePath);

            return self::FAILURE;
        }

        $agent = $this->buildAgent();

        $tests = [
            ['label' => 'auto-detected MIME', 'mime' => null],
            ['label' => 'audio/mp4 (M4A native)', 'mime' => 'audio/mp4'],
            ['label' => 'audio/aac (AAC bare)', 'mime' => 'audio/aac'],
            ['label' => 'audio/m4a (non-standard)', 'mime' => 'audio/m4a'],
        ];

        $winnerMime = null;
        $winnerLatency = null;

        foreach ($tests as $test) {
            [$success, $latencyMs] = $this->runMimeTest(
                $agent,
                $this->buildAttachment($sourcePath, $test['mime']),
                $test['label'],
                $test['mime'],
            );

            if ($success && $winnerMime === null) {
                $winnerMime = $test['mime'] ?? 'auto-detected';
                $winnerLatency = $latencyMs;
            }
        }

        $this->printVerdict($winnerMime, $winnerLatency);

        return $winnerMime !== null ? self::SUCCESS : self::FAILURE;
    }

    private function printHeader(string $sourcePath): void
    {
        $this->info('============================================');
        $this->info(' RND-01: Gemini Audio Compatibility Spike');
        $this->info('============================================');

        if (file_exists($sourcePath)) {
            $fileSizeKb = round(filesize($sourcePath) / 1024, 2);
            $detectedMime = (new Filesystem)->mimeType($sourcePath) ?: 'unknown';

            $this->line("  Audio file : {$sourcePath}");
            $this->line("  Size       : {$fileSizeKb} KB");
            $this->line("  Detected   : {$detectedMime}");
            $this->line('');
        }
    }

    private function buildAgent(): Agent&HasStructuredOutput
    {
        return new class implements Agent, HasStructuredOutput
        {
            use Promptable;

            public function instructions(): string
            {
                return 'You are a helpful assistant that analyses audio. Describe the audio briefly.';
            }

            /** @return array<string, mixed> */
            public function schema(JsonSchema $schema): array
            {
                return [
                    'audio_type' => $schema->string()->description('What kind of audio this is')->required(),
                    'description' => $schema->string()->description('Brief description of what you hear')->required(),
                ];
            }
        };
    }

    private function buildAttachment(string $sourcePath, ?string $mimeType): LocalAudio
    {
        return Audio::fromPath($sourcePath, $mimeType);
    }

    /**
     * @return array{bool, int}
     */
    private function runMimeTest(
        Agent&HasStructuredOutput $agent,
        LocalAudio $attachment,
        string $label,
        ?string $declaredMimeType,
    ): array {
        $this->line('--------------------------------------------');
        $this->line("  Test: {$label}");
        $this->line('  Declared : '.($declaredMimeType ?? '(filesystem-detected)'));

        $start = microtime(true);

        try {
            /** @var AgentResponse $response */
            $response = $agent->prompt(
                'What is this audio? Describe it briefly.',
                attachments: [$attachment],
                provider: Lab::Gemini,
                model: 'gemini-2.5-flash',
            );

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $this->info("  ✅ SUCCESS  ({$latencyMs}ms)");
            $this->line('  Response : '.substr($response->text, 0, 120));
            $this->line('');

            return [true, $latencyMs];
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $this->error("  ❌ FAILED  ({$latencyMs}ms)");
            $this->line('  Error    : '.$e->getMessage());
            $this->line('');

            return [false, $latencyMs];
        }
    }

    private function printVerdict(?string $winnerMime, ?int $winnerLatency): void
    {
        $this->line('============================================');

        if ($winnerMime === null) {
            $this->warn('  VERDICT: M4A rejected by Gemini ⚠️');
            $this->warn('  Action  : Add FFmpeg conversion step (see RND-01 plan).');
            $this->line('============================================');
            $this->line('');

            return;
        }

        $this->info('  VERDICT: Direct M4A accepted ✅');
        $this->info("  Use MIME : {$winnerMime}");
        $this->info("  Latency  : {$winnerLatency}ms");
        $this->info('  No FFmpeg conversion needed.');
    }
}
