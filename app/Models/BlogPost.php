<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlogPostStatus;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'topic',
        'title',
        'slug',
        'summary',
        'content',
        'image_path',
        'audio_path',
        'word_count',
        'status',
        'audio_failover_log',
        'published_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'audio_failover_log' => 'array',
            'published_at' => 'datetime',
            'word_count' => 'integer',
        ];
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /**
     * Full public URL for the featured image, routed through Storage::disk()
     * so switching to S3 requires only a config change.
     */
    public function imageUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
        );
    }

    /**
     * Full public URL for the audio transcript file.
     */
    public function audioUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->audio_path
                ? Storage::disk('public')->url($this->audio_path)
                : null,
        );
    }

    /**
     * Estimated reading time based on ~200 words per minute.
     */
    public function readingTimeMinutes(): Attribute
    {
        return Attribute::get(
            fn () => $this->word_count
                ? (int) ceil($this->word_count / 200)
                : null,
        );
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatus::Published)
            ->whereNotNull('published_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatus::Draft);
    }

    // ── Actions ────────────────────────────────────────────────────────────

    /**
     * Transition to Published and record the timestamp.
     */
    public function publish(): static
    {
        $this->update([
            'status' => BlogPostStatus::Published,
            'published_at' => now(),
        ]);

        return $this;
    }

    /**
     * Transition to Archived.
     */
    public function archive(): static
    {
        $this->update(['status' => BlogPostStatus::Archived]);

        return $this;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Generate a URL-safe slug from the title, guaranteed unique by appending
     * the first 8 characters of the UUID primary key.
     */
    public static function makeSlug(string $title, string $id): string
    {
        return Str::slug($title).'-'.substr($id, 0, 8);
    }
}
