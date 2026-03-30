<?php

declare(strict_types=1);

namespace App\Ai\Agents\Demo;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
final class BlogTextAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a professional blog writer. Given a topic, write a complete blog post with a catchy title, full article content in markdown format, and a one-sentence summary. Write 2-3 paragraphs of engaging, well-structured content.
PROMPT;

    public function instructions(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Catchy, SEO-friendly blog title.')
                ->required(),
            'content' => $schema->string()
                ->description('Full 3-4 paragraph blog post in markdown format. Do not include the title in the content.')
                ->required(),
            'summary' => $schema->string()
                ->description('One-sentence summary of the article suitable for a meta description.')
                ->required(),
        ];
    }
}
