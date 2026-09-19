<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Application\DTOs\MetricsFilterData;
use Modules\LeadScout\Domain\Ports\DecisionRuleRepositoryPort;
use Modules\LeadScout\Domain\Ports\FunnelMetricsReadPort;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Domain\Services\DecisionRuleEvaluator;
use Modules\LeadScout\Domain\ValueObjects\MetricsPeriod;

/**
 * Funnel metrics (spec US-6, FR-18/FR-33, T066): stage rates by channel,
 * variant, country, signal type and origin, weekly source volume, billed
 * hours/euros, qualified-lead cost, search/extraction effectiveness,
 * decisor and channel coverage, discovery quality by wave, and the
 * sample-size reading against the locked decision rule.
 *
 * Read-only: aggregates come from the read port; this handler only adds
 * the domain judgements (channel advice, decision-rule evaluation).
 */
final readonly class GetFunnelMetricsHandler
{
    public function __construct(
        private FunnelMetricsReadPort $metrics,
        private DecisionRuleRepositoryPort $rules,
        private ChannelAdvisor $advisor,
        private DecisionRuleEvaluator $evaluator,
        private Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public function handle(MetricsFilterData $filters): array
    {
        $period = new MetricsPeriod(
            from: $filters->from === null ? null : CarbonImmutable::parse($filters->from)->startOfDay(),
            to: $filters->to === null ? null : CarbonImmutable::parse($filters->to)->endOfDay(),
        );

        $totals = $this->metrics->totals($period);

        return [
            'stages' => $this->metrics->stageCounts($period),
            'totals' => $totals,
            'by_channel' => $this->metrics->byChannel($period),
            'by_variant' => $this->metrics->byVariant($period),
            'by_origin' => $this->metrics->byOrigin($period),
            'by_country' => $this->metrics->byCountry($period),
            'by_wave' => $this->metrics->waveQuality($period),
            'weekly_volume' => $this->metrics->weeklyVolume($period),
            'billing' => $this->metrics->billing($period),
            'cost_per_qualified_lead' => $this->metrics->costPerQualifiedLead(),
            'search_effectiveness' => $this->metrics->searchEffectiveness(),
            'extraction' => $this->metrics->extraction(),
            'decisor_coverage' => $this->decisorCoverage(),
            'sample' => $this->sample($totals['sent'], $totals['positive']),
        ];
    }

    /**
     * @return array{ab_leads: int, ab_with_decisor: int, ab_with_allowed_channel: float, reply_by_role: list<array{role: ?string, replied: int}>}
     */
    private function decisorCoverage(): array
    {
        $companies = $this->metrics->abCompaniesWithActiveChannels();
        $rules = (array) $this->config->get('lead-scout.contact_rules', []);
        $allowed = 0;

        foreach ($companies as $company) {
            $advice = $this->advisor->advise($company['channels'], [
                'country' => $company['country'],
                'has_offer' => true,
                'is_employment_offer' => false,
                'discovery_without_offer' => false,
                'has_decisor' => true,
                'nominative_email' => null,
                'dgc_listed' => false,
                'dgc_list_stale' => false,
            ], $rules);

            foreach ($advice['ranked'] as $candidate) {
                if ($candidate['allowed']) {
                    $allowed++;

                    break;
                }
            }
        }

        $abLeads = count($companies);

        return [
            'ab_leads' => $abLeads,
            'ab_with_decisor' => $this->metrics->abCompaniesWithDecisor(),
            'ab_with_allowed_channel' => $abLeads > 0 ? round($allowed / $abLeads, 4) : 0.0,
            'reply_by_role' => $this->metrics->repliesByRole(),
        ];
    }

    /**
     * @return array{outcome: string, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}
     */
    private function sample(int $sent, int $positive): array
    {
        $rule = $this->rules->latestLocked();

        $evaluation = $this->evaluator->evaluate(
            [
                'sample_size' => $rule?->sampleSize ?? 150,
                'window_days' => $rule?->windowDays ?? 56,
                'thresholds' => $rule?->thresholds ?? ['scale_at' => 0.05, 'stop_below' => 0.01],
            ],
            $sent,
            $positive,
        );

        return [
            'outcome' => $evaluation['outcome']->value,
            'contacted' => $sent,
            'positives' => $positive,
            'rate' => $sent > 0 ? round($positive / $sent, 4) : 0.0,
            'expected_low' => $evaluation['expected_low'],
            'expected_high' => $evaluation['expected_high'],
            'conclusive' => $evaluation['conclusive'],
            'message' => $evaluation['message'],
        ];
    }
}
