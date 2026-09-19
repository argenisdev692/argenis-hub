<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\ScoreResult;
use Modules\LeadScout\Domain\Enums\DiscardReason;
use Modules\LeadScout\Domain\Enums\Tier;

interface ScoreResultRepositoryPort
{
    /**
     * Stores the new current verdict with its reasons and retires the
     * previous one, atomically.
     *
     * @param  array<string, int>  $subscores
     * @param  list<array{signal_id: ?int, points: int, explanation: string}>  $reasons
     */
    public function recordCurrent(
        int $companyId,
        ?int $profileId,
        string $rulesVersion,
        array $subscores,
        int $leadScore,
        int $confidence,
        Tier $tier,
        ?DiscardReason $discardReason,
        array $reasons,
    ): ScoreResult;

    public function currentFor(int $companyId): ?ScoreResult;

    public function currentTier(int $companyId): ?Tier;
}
