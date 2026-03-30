<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
final class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);
        $content = implode("\n\n", $this->faker->paragraphs(5));

        return [
            'topic' => $this->faker->sentence(4),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(8),
            'summary' => $this->faker->sentence(20),
            'content' => $content,
            'image_path' => null,
            'audio_path' => null,
            'word_count' => str_word_count($content),
            'status' => BlogPostStatus::Draft,
            'audio_failover_log' => null,
            'published_at' => null,
        ];
    }

    /** Post is mid-generation — no content yet. */
    public function generating(): static
    {
        return $this->state([
            'status' => BlogPostStatus::Generating,
            'title' => null,
            'slug' => null,
            'content' => null,
            'summary' => null,
        ]);
    }

    /** Post is published and visible. */
    public function published(): static
    {
        return $this->state([
            'status' => BlogPostStatus::Published,
            'published_at' => now(),
        ]);
    }

    /** Post is archived. */
    public function archived(): static
    {
        return $this->state(['status' => BlogPostStatus::Archived]);
    }

    /** Post has a featured image stored. */
    public function withImage(): static
    {
        return $this->state([
            'image_path' => 'demo/images/'.Str::random(40).'.jpg',
        ]);
    }

    /** Post has an audio file and a successful failover log. */
    public function withAudio(): static
    {
        return $this->state([
            'audio_path' => 'demo/audio/'.Str::random(40).'.mp3',
            'audio_failover_log' => [
                ['provider' => 'OpenAI', 'status' => 'success', 'message' => 'Audio generated successfully.'],
            ],
        ]);
    }
}
