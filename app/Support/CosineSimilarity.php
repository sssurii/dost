<?php

declare(strict_types=1);

namespace App\Support;

final class CosineSimilarity
{
    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     */
    public static function calculate(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $n = count($a); $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0 ? $dot / $denominator : 0.0;
    }
}
