<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;

/**
 * Deterministic proof-point picker (plan §3.4, no RAG): the CV project with
 * the largest technology overlap with the company signals wins; ties break
 * by recency (higher `order` = more recent). Explicable and unit-testable.
 */
final readonly class ProofPointMatcher
{
    /**
     * @param  array<int, array{title: string, summary: string, technologies: list<string>, sector: ?string, result: ?string, url: ?string, order: int}>  $proofPoints
     * @param  list<string>  $companyTechnologies
     * @return array{title: string, summary: string, technologies: list<string>, sector: ?string, result: ?string, url: ?string, order: int}|null
     */
    #[\NoDiscard]
    public function match(array $proofPoints, array $companyTechnologies, ?string $companySector): ?array
    {
        if ($proofPoints === []) {
            return null;
        }

        $companyTech = array_map(strtolower(...), $companyTechnologies);
        $best = null;
        $bestScore = -1;

        foreach ($proofPoints as $point) {
            $overlap = count(array_intersect(
                array_map(strtolower(...), $point['technologies']),
                $companyTech,
            ));

            $score = $overlap * 10
                + ($companySector !== null && $point['sector'] === $companySector ? 3 : 0);

            if ($score > $bestScore || ($score === $bestScore && $best !== null && $point['order'] > $best['order'])) {
                $best = $point;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Technologies of a free-text block, via the shared taxonomy.
     *
     * @return list<string>
     */
    #[\NoDiscard('Detected technologies must be captured')]
    public static function detectTechnologies(string $text): array
    {
        $found = [];

        foreach (SkillTaxonomy::TERMS as $canonical => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (SkillTaxonomy::mentions($text, $synonym)) {
                    $found[] = $canonical;

                    break;
                }
            }
        }

        sort($found);

        return $found;
    }
}
