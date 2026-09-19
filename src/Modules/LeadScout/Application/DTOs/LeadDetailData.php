<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Bandeja detail (plan §5 `LeadDetailData`): company, origin postings,
 * score with evidence-linked reasons, decisors with evidence, ranked
 * channels and outreach history. One payload, ≤ 5 queries (N+1 rule).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class LeadDetailData extends Data
{
    /**
     * @param  list<array{uuid: string, title: string, status: string, source_url: string}>  $postings
     * @param  array{subscores: array<string, int>, lead_score: ?int, confidence: ?int, tier: ?string, discard_reason: ?string, rules_version: ?string}|null  $score
     * @param  list<array{points: int, explanation: string, signal_key: ?string, evidence_url: ?string, evidence_excerpt: ?string}>  $reasons
     * @param  list<ContactData>  $decisors
     * @param  list<array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string, audience: ?string, evidence_url: ?string}>  $channels
     * @param  list<OutreachData>  $outreaches
     * @param  array{uuid: string, name: string, domain: string, country: ?string, company_type: ?string, origin: string, origin_ref: ?string, discovery_wave: ?string, employee_range: string, team_size_observed: ?int, has_decision_maker: bool, needs_research: bool, activity_status: string, legal_name: ?string, city: ?string, founded_year: ?int, services: list<string>, sectors: list<string>}  $company
     */
    public function __construct(
        public readonly array $company,
        public readonly array $postings,
        public readonly ?array $score,
        public readonly array $reasons,
        public readonly array $decisors,
        public readonly array $channels,
        public readonly array $outreaches,
        public readonly bool $coldEmailAllowed,
        public readonly bool $employmentApplication,
    ) {}
}
