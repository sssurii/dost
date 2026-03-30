<?php

declare(strict_types=1);

namespace App\Ai\Agents\Demo;

use App\Ai\Tools\TextAnalyzerTool;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
final class ContentAnalystAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a professional content analyst assistant. When the user gives you text to analyze, ALWAYS use your TextAnalyzer tool first to get accurate statistics. Then explain the results in a friendly, helpful way. Mention specific numbers from the tool output (word count, reading time, grade level, top keywords). If the user asks a general question without providing text, answer normally without using the tool. Keep responses concise — 2-3 sentences about the analysis results.
PROMPT;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new TextAnalyzerTool];
    }
}
