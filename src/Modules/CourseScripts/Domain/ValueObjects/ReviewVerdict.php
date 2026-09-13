<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * An independent reviewer's scores (0–10 per dimension) and targeted
 * objections (FR-40, FR-44).
 */
final readonly class ReviewVerdict
{
    /**
     * @param  array<string, int>  $scores
     * @param  list<array{target: string, text: string}>  $objections
     */
    public function __construct(
        public array $scores,
        public array $objections,
    ) {}

    public function average(): float
    {
        return $this->scores === [] ? 0.0 : array_sum($this->scores) / count($this->scores);
    }

    public function passes(int $minDimension, int $minOverall): bool
    {
        return $this->scores !== []
            && min($this->scores) >= $minDimension
            && $this->average() >= $minOverall;
    }
}
