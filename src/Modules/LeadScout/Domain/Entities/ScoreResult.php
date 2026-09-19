<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use Modules\LeadScout\Domain\Enums\DiscardReason;
use Modules\LeadScout\Domain\Enums\Tier;

/**
 * One scoring verdict for a company (spec US-4). Only the newest is
 * current; older ones stay for auditability with their rules version.
 */
final readonly class ScoreResult
{
    /**
     * @param  array<string, int>  $subscores
     * @param  list<array{signal_key: ?string, points: int, explanation: string, evidence_url: ?string, evidence_excerpt: ?string}>  $reasons
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $companyId,
        public ?int $profileId,
        public string $rulesVersion,
        public array $subscores,
        public int $leadScore,
        public int $confidence,
        public Tier $tier,
        public ?DiscardReason $discardReason,
        public array $reasons,
    ) {}
}
