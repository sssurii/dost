<?php

namespace Database\Factories;

use App\Models\DemoDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoDocument>
 */
class DemoDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'content' => fake()->paragraphs(2, true),
            'embedding' => null,
        ];
    }
}
