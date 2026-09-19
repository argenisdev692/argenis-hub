<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\DecisionRule;
use Modules\LeadScout\Domain\Enums\DecisionOutcome;

interface DecisionRuleRepositoryPort
{
    /**
     * @param  array<string, float>  $thresholds
     */
    public function create(int $sampleSize, int $windowDays, array $thresholds): DecisionRule;

    public function byUuid(string $uuid): ?DecisionRule;

    public function latestLocked(): ?DecisionRule;

    public function lock(
        DecisionRule $rule,
        DateTimeImmutable $lockedAt,
        DateTimeImmutable $periodEndsAt,
        DecisionOutcome $result,
    ): DecisionRule;
}
