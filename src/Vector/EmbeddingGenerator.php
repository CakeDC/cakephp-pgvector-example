<?php
declare(strict_types=1);

namespace App\Vector;

class EmbeddingGenerator
{
    private const DIMENSIONS = 128;

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);

        $normalized = strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
        $tokens = array_filter(explode(' ', $normalized), static fn (string $t): bool => $t !== '');

        foreach ($tokens as $token) {
            $bucket = crc32($token) % self::DIMENSIONS;
            $vector[$bucket] += 1.0;
        }

        $magnitude = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
        if ($magnitude > 0.0) {
            $vector = array_map(static fn (float $v): float => $v / $magnitude, $vector);
        }

        return $vector;
    }
}
