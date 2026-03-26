<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Recording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupAudio extends Command
{
    protected $signature = 'audio:cleanup
                            {--dry-run : Preview what would be cleaned without making changes}
                            {--user= : Limit cleanup to a specific user ID}';

    protected $description = 'Delete expired audio files and null-out paths. Keeps DB rows for stats (Option C).';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');

        $this->info($isDryRun
            ? '🔍 DRY RUN — no changes will be made'
            : '🧹 Starting audio cleanup...',
        );

        $query = Recording::query()
            ->where('expires_at', '<', now())
            ->where(function ($q): void {
                $q->whereNotNull('path')
                    ->orWhereNotNull('ai_response_audio_path');
            });

        if ($userId !== null) {
            $query->where('user_id', (int) $userId);
        }

        $stats = ['files' => 0, 'bytes' => 0, 'rows' => 0];

        $query->chunkById(100, function ($recordings) use ($isDryRun, &$stats): void {
            foreach ($recordings as $recording) {
                $this->line("  → Recording #{$recording->id} (user {$recording->user_id})");

                $updates = [];

                if ($recording->path !== null) {
                    $updates['path'] = null;
                    $stats['bytes'] += $this->deleteFile($recording->path, $isDryRun);
                    $stats['files']++;
                }

                if ($recording->ai_response_audio_path !== null) {
                    $updates['ai_response_audio_path'] = null;
                    $stats['bytes'] += $this->deleteFile($recording->ai_response_audio_path, $isDryRun);
                    $stats['files']++;
                }

                if (! $isDryRun && ! empty($updates)) {
                    $recording->update($updates);
                    $stats['rows']++;
                }
            }
        });

        $this->newLine();
        $this->info('✅ Cleanup complete:');
        $this->table(['Metric', 'Value'], [
            ['Audio files deleted',             $stats['files']],
            ['DB rows updated (paths nulled)',   $stats['rows']],
            ['Storage freed',                   round($stats['bytes'] / 1024 / 1024, 2).' MB'],
        ]);

        if ($isDryRun) {
            $this->warn('(DRY RUN — nothing was actually changed)');
        }

        return self::SUCCESS;
    }

    /**
     * Delete a file from the public disk and return the bytes freed.
     */
    private function deleteFile(string $path, bool $isDryRun): int
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $this->line("    ⚠  File already missing: {$path}");

            return 0;
        }

        $bytes = $disk->size($path);

        if (! $isDryRun) {
            $disk->delete($path);
        }

        $this->line('    ✓ '.($isDryRun ? '[DRY] ' : '')."Deleted ({$bytes} bytes): {$path}");

        return $bytes;
    }
}
