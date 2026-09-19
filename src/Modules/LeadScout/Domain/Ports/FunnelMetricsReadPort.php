<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\ValueObjects\MetricsPeriod;

/**
 * Aggregate reads behind the funnel dashboard (spec US-6, FR-18/FR-33).
 * Every method is side-effect free and aggregates in the database.
 * "Responded" and "positive" follow `OutreachStage::respondedOutcomes()`
 * and `positiveOutcomes()`.
 */
interface FunnelMetricsReadPort
{
    /**
     * @return list<array{stage: string, count: int}>
     */
    public function stageCounts(MetricsPeriod $period): array;

    /**
     * @return array{sent: int, responded: int, positive: int}
     */
    public function totals(MetricsPeriod $period): array;

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    public function byChannel(MetricsPeriod $period): array;

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    public function byVariant(MetricsPeriod $period): array;

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    public function byOrigin(MetricsPeriod $period): array;

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    public function byCountry(MetricsPeriod $period): array;

    /**
     * @return list<array{wave: ?string, companies: int, discarded_by_reason: array<string, int>, ab_leads: int, response_rate: float}>
     */
    public function waveQuality(MetricsPeriod $period): array;

    /**
     * @return list<array{week: string, source: string, postings: int}>
     */
    public function weeklyVolume(MetricsPeriod $period): array;

    /**
     * @return array{hours_billed: int, revenue_cents: int}
     */
    public function billing(MetricsPeriod $period): array;

    /**
     * @return array{spent_micros: int, qualified_leads: int, micros_per_lead: ?int}
     */
    public function costPerQualifiedLead(): array;

    /**
     * @return list<array{family: ?string, queries: int, results: int, new_companies: int, ab_leads: int}>
     */
    public function searchEffectiveness(): array;

    /**
     * @return array{companies: int, with_pages: int, blocked_share: float, spa_rescued_share: float, micros_per_enriched: ?int}
     */
    public function extraction(): array;

    /**
     * Tier A/B companies with their active channels, for channel-coverage advice.
     *
     * @return list<array{country: ?string, channels: list<array{uuid: string, type: string, url: ?string, generic_email: ?string, status: string, audience: ?string}>}>
     */
    public function abCompaniesWithActiveChannels(): array;

    /** Tier A/B companies with at least one live decisor. */
    public function abCompaniesWithDecisor(): int;

    /**
     * @return list<array{role: ?string, replied: int}>
     */
    public function repliesByRole(): array;
}
