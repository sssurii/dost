<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordingStatus;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recording>
 */
final class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'path' => 'recordings/1/rec_'.time().'.m4a',
            'mime_type' => 'audio/mp4',
            'duration_seconds' => fake()->numberBetween(1, 60),
            'file_size_bytes' => fake()->numberBetween(10000, 500000),
            'status' => RecordingStatus::Pending,
            'transcript' => null,
            'ai_response_text' => null,
            'ai_response_audio_path' => null,
            'expires_at' => now()->addDays(2),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => RecordingStatus::Pending]);
    }

    public function processing(): static
    {
        return $this->state(['status' => RecordingStatus::Processing]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => RecordingStatus::Completed,
            'transcript' => fake()->sentence(),
            'ai_response_text' => fake()->sentence(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => RecordingStatus::Failed]);
    }
}
