<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\DecisionOutcome;

/**
 * Decision rule fixed before the first measured contact (spec US-6 CA-3,
 * FR-19). A locked rule is immutable.
 */
final readonly class DecisionRule
{
    /**
     * @param  array<string, float>  $thresholds
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $sampleSize,
        public int $windowDays,
        public array $thresholds,
        public ?DateTimeImmutable $lockedAt,
        public ?DecisionOutcome $result,
    ) {}

    public function isLocked(): bool
    {
        return $this->lockedAt !== null;
    }
}
