<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Pure-PHP trigram similarity compatible with `pg_trgm` semantics, for the
 * resolve-to-source tier-1 title match (T-118). The database stays the
 * authority for indexed search; this decides in-process matches.
 */
final readonly class TrigramSimilarity
{
    #[\NoDiscard]
    public function similarity(string $left, string $right): float
    {
        $a = self::trigrams($left);
        $b = self::trigrams($right);

        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));

        return $intersection / max(count($a), count($b));
    }

    /** @return list<string> */
    private static function trigrams(string $value): array
    {
        $normalized = '  '.PostingFingerprint::normalize($value).'  ';
        $length = mb_strlen($normalized);
        $trigrams = [];

        for ($i = 0; $i < $length - 2; $i++) {
            $trigrams[] = mb_substr($normalized, $i, 3);
        }

        return array_values(array_unique($trigrams));
    }
}
