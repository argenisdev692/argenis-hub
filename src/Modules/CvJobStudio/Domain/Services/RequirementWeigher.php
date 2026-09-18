<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;

/**
 * Tag weights (required 1.0 / preferred 0.6 / bonus 0.25) with the soft-skill
 * denominator cap: soft weights may contribute at most `soft_cap_ratio` of
 * the hard total, scaled down proportionally when capped (FR-16).
 */
final readonly class RequirementWeigher
{
    /**
     * @param  array<int, array{tag: string, nature: string}>  $requirements
     * @param  array<string, float>  $tagWeights
     * @return array{hard_total: float, soft_total: float, weights: array<int, float>}
     */
    #[\NoDiscard]
    public function weigh(array $requirements, array $tagWeights, float $softCapRatio): array
    {
        $raw = [];
        $hardTotal = 0.0;
        $softRaw = 0.0;

        foreach ($requirements as $index => $requirement) {
            $tag = RequirementTag::tryFrom($requirement['tag']) ?? RequirementTag::Required;
            $weight = $tagWeights[$tag->value] ?? 0.0;
            $raw[$index] = $weight;

            match (RequirementNature::tryFrom($requirement['nature']) ?? RequirementNature::Hard) {
                RequirementNature::Hard => $hardTotal += $weight,
                RequirementNature::Soft => $softRaw += $weight,
            };
        }

        $softTotal = min($softRaw, $softCapRatio * $hardTotal);
        $scale = $softRaw > 0.0 ? $softTotal / $softRaw : 0.0;

        $weights = [];

        foreach ($requirements as $index => $requirement) {
            $nature = RequirementNature::tryFrom($requirement['nature']) ?? RequirementNature::Hard;
            $weights[$index] = $nature === RequirementNature::Soft ? $raw[$index] * $scale : $raw[$index];
        }

        return ['hard_total' => $hardTotal, 'soft_total' => $softTotal, 'weights' => $weights];
    }
}
