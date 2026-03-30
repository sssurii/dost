<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class TextAnalyzerTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Analyzes text and returns statistics: word count, sentence count, paragraph count, average words per sentence, reading time, top keywords, and readability score.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        /** @var string $text */
        $text = $request['text'] ?? '';
        $text = trim($text);

        if ($text === '') {
            return json_encode(['error' => 'No text provided'], JSON_THROW_ON_ERROR);
        }

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sentenceCount = count($sentences);

        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $paragraphCount = max(1, count($paragraphs));

        $avgWordsPerSentence = $sentenceCount > 0
            ? round($wordCount / $sentenceCount, 1)
            : 0.0;

        $readingTimeSeconds = (int) round(($wordCount / 200) * 60);

        $topKeywords = $this->extractKeywords($words);
        $fleschScore = $this->fleschReadingEase($words, $sentenceCount);
        $gradeLevel = $this->gradeLevel($fleschScore);

        return (string) json_encode([
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'paragraph_count' => $paragraphCount,
            'avg_words_per_sentence' => $avgWordsPerSentence,
            'reading_time_seconds' => $readingTimeSeconds,
            'top_keywords' => $topKeywords,
            'flesch_reading_ease' => $fleschScore,
            'grade_level' => $gradeLevel,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()
                ->description('The text content to analyze.')
                ->required(),
        ];
    }

    /**
     * @param  string[]  $words
     * @return string[]
     */
    private function extractKeywords(array $words): array
    {
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'for', 'on', 'with', 'at',
            'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'and',
            'but', 'or', 'nor', 'not', 'so', 'yet', 'both', 'either', 'neither', 'each',
            'every', 'all', 'any', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
            'only', 'own', 'same', 'than', 'too', 'very', 'just', 'because', 'about',
            'it', 'its', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'we',
            'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'our', 'their'];

        $counts = [];
        foreach ($words as $word) {
            $lower = mb_strtolower(preg_replace('/[^a-zA-Z]/', '', $word) ?? '');
            if (mb_strlen($lower) > 2 && ! in_array($lower, $stopWords, true)) {
                $counts[$lower] = ($counts[$lower] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, 5);
    }

    /**
     * @param  string[]  $words
     */
    private function fleschReadingEase(array $words, int $sentenceCount): float
    {
        if ($sentenceCount === 0 || count($words) === 0) {
            return 0.0;
        }

        $totalSyllables = 0;
        foreach ($words as $word) {
            $totalSyllables += $this->countSyllables($word);
        }

        $wordCount = count($words);
        $score = 206.835 - (1.015 * ($wordCount / $sentenceCount)) - (84.6 * ($totalSyllables / $wordCount));

        return round(max(0, min(100, $score)), 1);
    }

    private function countSyllables(string $word): int
    {
        $word = mb_strtolower(preg_replace('/[^a-z]/', '', $word) ?? '');

        if (mb_strlen($word) <= 2) {
            return 1;
        }

        $count = (int) preg_match_all('/[aeiouy]+/', $word);

        if (str_ends_with($word, 'e') && ! str_ends_with($word, 'le')) {
            $count--;
        }

        return max(1, $count);
    }

    private function gradeLevel(float $fleschScore): string
    {
        return match (true) {
            $fleschScore >= 90 => '5th grade',
            $fleschScore >= 80 => '6th grade',
            $fleschScore >= 70 => '7th grade',
            $fleschScore >= 60 => '8th grade',
            $fleschScore >= 50 => '10th grade',
            $fleschScore >= 30 => 'College',
            default => 'Graduate',
        };
    }
}
