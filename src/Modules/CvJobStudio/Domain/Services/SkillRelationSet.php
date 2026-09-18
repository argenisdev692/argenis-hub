<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Modules\CvJobStudio\Domain\Enums\SkillRelationKind;

/**
 * Candidate-confirmed skill relations (FR-44). Credit ladder: 1.0 for an
 * exact name or a CONFIRMED alias, 0.5 for a CONFIRMED family link, 0.0
 * otherwise. A pending proposal never grants credit — pending rows never
 * reach this set (the repository filters them out).
 */
final readonly class SkillRelationSet
{
    /**
     * @param  array<string, true>  $cvSkills  normalized canonical CV skill names
     * @param  array<int, array{from: string, to: string, kind: string}>  $confirmedRelations
     */
    public function __construct(
        private array $cvSkills,
        private array $confirmedRelations = [],
    ) {}

    #[\NoDiscard]
    public function creditFor(string $requirement): float
    {
        $key = self::normalize($requirement);

        if (isset($this->cvSkills[$key])) {
            return 1.0;
        }

        $best = 0.0;

        foreach ($this->confirmedRelations as $relation) {
            if (self::normalize($relation['from']) !== $key) {
                continue;
            }

            if (! isset($this->cvSkills[self::normalize($relation['to'])])) {
                continue;
            }

            $credit = match ($relation['kind']) {
                SkillRelationKind::Alias->value => 1.0,
                SkillRelationKind::Family->value => 0.5,
                default => 0.0,
            };

            $best = max($best, $credit);
        }

        return $best;
    }

    #[\NoDiscard]
    public static function normalize(string $skill): string
    {
        return mb_strtolower(trim($skill));
    }
}
