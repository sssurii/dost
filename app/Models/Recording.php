<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordingStatus;
use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property RecordingStatus $status
 * @property string|null $path
 * @property string|null $ai_response_audio_path
 * @property Carbon|null $expires_at
 */
final class Recording extends Model
{
    /** @use HasFactory<RecordingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'path',
        'mime_type',
        'duration_seconds',
        'file_size_bytes',
        'status',
        'transcript',
        'ai_response_text',
        'ai_response_audio_path',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecordingStatus::class,
            'expires_at' => 'datetime',
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullPathAttribute(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return Storage::disk('public')->path($this->path);
    }

    public function getPublicUrlAttribute(): ?string
    {
        if ($this->path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }

    public function isPending(): bool
    {
        return $this->status === RecordingStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === RecordingStatus::Processing;
    }

    public function isCompleted(): bool
    {
        return $this->status === RecordingStatus::Completed;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => RecordingStatus::Processing]);
    }

    public function markAsCompleted(string $transcript, string $aiResponse): void
    {
        $this->update([
            'status' => RecordingStatus::Completed,
            'transcript' => $transcript,
            'ai_response_text' => $aiResponse,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => RecordingStatus::Failed]);
    }
}
