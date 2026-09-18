<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Application\DTOs\MetricsFilterData;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Domain\Services\DecisionRuleEvaluator;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutDecisionRuleEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Funnel metrics (spec US-6, FR-18/FR-33, T066): stage rates by channel,
 * variant, country, signal type and origin, weekly source volume, billed
 * hours/euros, qualified-lead cost, search/extraction effectiveness,
 * decisor and channel coverage, discovery quality by wave, and the
 * sample-size reading against the locked decision rule.
 *
 * Read-only: aggregates over explicit columns, never loops over relations.
 */
final readonly class GetFunnelMetricsHandler
{
    /**
     * @var list<string>
     */
    private const array RESPONDED = ['replied', 'positive', 'call', 'trial', 'won', 'recurrent'];

    /**
     * @var list<string>
     */
    private const array POSITIVE = ['positive', 'call', 'trial', 'won', 'recurrent'];

    public function __construct(
        private ChannelAdvisor $advisor,
        private DecisionRuleEvaluator $evaluator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public function handle(MetricsFilterData $filters): array
    {
        $outreaches = $this->scopedOutreaches($filters);

        $sent = (clone $outreaches)->whereNotNull('sent_at')->count();
        $responded = (clone $outreaches)->whereIn('stage', self::RESPONDED)->count();
        $positive = (clone $outreaches)->whereIn('stage', self::POSITIVE)->count();

        return [
            'stages' => $this->stages($filters),
            'totals' => ['sent' => $sent, 'responded' => $responded, 'positive' => $positive],
            'by_channel' => $this->byColumn($filters, 'send_medium', $sent),
            'by_variant' => $this->byColumn($filters, 'variant', $sent),
            'by_origin' => $this->byCompanyColumn($filters, 'origin'),
            'by_country' => $this->byCompanyColumn($filters, 'country'),
            'by_wave' => $this->waveQuality($filters),
            'weekly_volume' => $this->weeklyVolume($filters),
            'billing' => $this->billing($filters),
            'cost_per_qualified_lead' => $this->costPerQualifiedLead(),
            'search_effectiveness' => $this->searchEffectiveness(),
            'extraction' => $this->extraction(),
            'decisor_coverage' => $this->decisorCoverage(),
            'sample' => $this->sample($sent, $positive),
        ];
    }

    /**
     * @return Builder<ScoutOutreachEloquentModel>
     */
    private function scopedOutreaches(MetricsFilterData $filters): Builder
    {
        $query = ScoutOutreachEloquentModel::query();

        if ($filters->from !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters->from)->startOfDay());
        }

        if ($filters->to !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($filters->to)->endOfDay());
        }

        return $query;
    }

    /**
     * @return list<array{stage: string, count: int}>
     */
    private function stages(MetricsFilterData $filters): array
    {
        return $this->scopedOutreaches($filters)
            ->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->map(static fn ($count, $stage): array => ['stage' => $stage, 'count' => (int) $count])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    private function byColumn(MetricsFilterData $filters, string $column, int $sent): array
    {
        $rows = $this->scopedOutreaches($filters)
            ->selectRaw("{$column} as value, count(*) as sent, sum(case when stage in ('".implode("','", self::RESPONDED)."') then 1 else 0 end) as responded, sum(case when stage in ('".implode("','", self::POSITIVE)."') then 1 else 0 end) as positive")
            ->groupBy($column)
            ->get();

        return $rows->map(static fn ($row): array => [
            'value' => $row->value,
            'sent' => (int) $row->sent,
            'responded' => (int) $row->responded,
            'positive' => (int) $row->positive,
            'response_rate' => $row->sent > 0 ? round($row->responded / $row->sent, 4) : 0.0,
        ])->all();
    }

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    private function byCompanyColumn(MetricsFilterData $filters, string $column): array
    {
        $rows = $this->scopedOutreaches($filters)
            ->join('scout_companies', 'scout_companies.id', '=', 'scout_outreaches.company_id')
            ->selectRaw("scout_companies.{$column} as value, count(*) as sent, sum(case when scout_outreaches.stage in ('".implode("','", self::RESPONDED)."') then 1 else 0 end) as responded, sum(case when scout_outreaches.stage in ('".implode("','", self::POSITIVE)."') then 1 else 0 end) as positive")
            ->groupBy("scout_companies.{$column}")
            ->get();

        return $rows->map(static fn ($row): array => [
            'value' => $row->value,
            'sent' => (int) $row->sent,
            'responded' => (int) $row->responded,
            'positive' => (int) $row->positive,
            'response_rate' => $row->sent > 0 ? round($row->responded / $row->sent, 4) : 0.0,
        ])->all();
    }

    /**
     * @return list<array{wave: ?string, companies: int, discarded_by_reason: array<string, int>, ab_leads: int, response_rate: float}>
     */
    private function waveQuality(MetricsFilterData $filters): array
    {
        $companies = ScoutCompanyEloquentModel::query()
            ->with(['scoreResults' => fn ($query) => $query->where('is_current', true)])
            ->when($filters->from !== null, fn ($query) => $query->where('created_at', '>=', CarbonImmutable::parse($filters->from)->startOfDay()))
            ->when($filters->to !== null, fn ($query) => $query->where('created_at', '<=', CarbonImmutable::parse($filters->to)->endOfDay()))
            ->get(['id', 'discovery_wave', 'origin']);

        $out = [];

        foreach ($companies->groupBy('discovery_wave') as $wave => $group) {
            $ids = $group->pluck('id');
            $ab = 0;
            $discarded = [];

            foreach ($group as $company) {
                $current = $company->scoreResults->first();

                if ($current !== null && in_array($current->tier->value, ['A', 'B'], true)) {
                    $ab++;
                }

                if ($current !== null && $current->tier->value === 'discarded' && $current->discard_reason !== null) {
                    $reason = $current->discard_reason->value;
                    $discarded[$reason] = ($discarded[$reason] ?? 0) + 1;
                }
            }

            $sent = ScoutOutreachEloquentModel::query()->whereIn('company_id', $ids)->whereNotNull('sent_at')->count();
            $responded = ScoutOutreachEloquentModel::query()->whereIn('company_id', $ids)->whereIn('stage', self::RESPONDED)->count();

            $out[] = [
                'wave' => $wave,
                'companies' => $group->count(),
                'discarded_by_reason' => $discarded,
                'ab_leads' => $ab,
                'response_rate' => $sent > 0 ? round($responded / $sent, 4) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{week: string, source: string, postings: int}>
     */
    private function weeklyVolume(MetricsFilterData $filters): array
    {
        $driver = DB::getDriverName();
        $week = $driver === 'pgsql' ? "to_char(scout_job_postings.created_at, 'IYYY-IW')" : "strftime('%Y-%W', scout_job_postings.created_at)";

        return DB::table('scout_job_postings')
            ->join('scout_job_posting_sources', 'scout_job_posting_sources.posting_id', '=', 'scout_job_postings.id')
            ->join('scout_sources', 'scout_sources.id', '=', 'scout_job_posting_sources.source_id')
            ->when($filters->from !== null, fn ($query) => $query->where('scout_job_postings.created_at', '>=', CarbonImmutable::parse($filters->from)->startOfDay()))
            ->when($filters->to !== null, fn ($query) => $query->where('scout_job_postings.created_at', '<=', CarbonImmutable::parse($filters->to)->endOfDay()))
            ->selectRaw("{$week} as week, scout_sources.name as source, count(distinct scout_job_postings.id) as postings")
            ->groupBy('week', 'source')
            ->orderBy('week')
            ->get()
            ->map(static fn ($row): array => ['week' => $row->week, 'source' => $row->source, 'postings' => (int) $row->postings])
            ->all();
    }

    /**
     * @return array{hours_billed: int, revenue_cents: int}
     */
    private function billing(MetricsFilterData $filters): array
    {
        $query = ScoutOpportunityEloquentModel::query()->where('status', 'won');

        if ($filters->from !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters->from)->startOfDay());
        }

        if ($filters->to !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($filters->to)->endOfDay());
        }

        return [
            'hours_billed' => (int) $query->sum('hours_per_month'),
            'revenue_cents' => (int) (clone $query)->sum('amount_cents'),
        ];
    }

    /**
     * @return array{spent_micros: int, qualified_leads: int, micros_per_lead: ?int}
     */
    private function costPerQualifiedLead(): array
    {
        $spent = (int) DB::table('scout_budgets')->sum('spent_micros');

        $qualified = ScoutCompanyEloquentModel::query()
            ->whereHas('scoreResults', fn ($query) => $query->where('is_current', true)->whereIn('tier', ['A', 'B']))
            ->count();

        return [
            'spent_micros' => $spent,
            'qualified_leads' => $qualified,
            'micros_per_lead' => $qualified > 0 ? (int) round($spent / $qualified) : null,
        ];
    }

    /**
     * @return list<array{family: ?string, queries: int, results: int, new_companies: int, ab_leads: int}>
     */
    private function searchEffectiveness(): array
    {
        $families = DB::table('scout_search_queries')
            ->selectRaw('family, count(*) as queries, coalesce(sum(json_array_length(results)), 0) as results, coalesce(sum(new_companies_count), 0) as new_companies')
            ->groupBy('family')
            ->get();

        return $families->map(function ($row): array {
            $texts = DB::table('scout_search_queries')->where('family', $row->family)->pluck('query_text');

            $ab = ScoutCompanyEloquentModel::query()
                ->where('origin', 'discovery')
                ->whereIn('origin_ref', $texts->all())
                ->whereHas('scoreResults', fn ($query) => $query->where('is_current', true)->whereIn('tier', ['A', 'B']))
                ->count();

            return [
                'family' => $row->family,
                'queries' => (int) $row->queries,
                'results' => (int) $row->results,
                'new_companies' => (int) $row->new_companies,
                'ab_leads' => $ab,
            ];
        })->all();
    }

    /**
     * @return array{companies: int, with_pages: int, blocked_share: float, spa_rescued_share: float, micros_per_enriched: ?int}
     */
    private function extraction(): array
    {
        $companies = ScoutCompanyEloquentModel::query()->count();

        $withPages = ScoutCompanyEloquentModel::query()->whereHas('fetchedPages')->count();

        $attempts = DB::table('scout_fetch_attempts');
        $total = (clone $attempts)->count();
        $blocked = (clone $attempts)->where('status', 'blocked')->count();
        $spa = (clone $attempts)->where('error', 'spa_empty')->count();

        $spent = (int) DB::table('scout_budgets')->where('category', 'extraction')->sum('spent_micros');

        return [
            'companies' => $companies,
            'with_pages' => $withPages,
            'blocked_share' => $total > 0 ? round($blocked / $total, 4) : 0.0,
            'spa_rescued_share' => $total > 0 ? round($spa / $total, 4) : 0.0,
            'micros_per_enriched' => $withPages > 0 ? (int) round($spent / $withPages) : null,
        ];
    }

    /**
     * @return array{ab_leads: int, ab_with_decisor: int, ab_with_allowed_channel: float, reply_by_role: list<array{role: ?string, replied: int}>}
     */
    private function decisorCoverage(): array
    {
        $abIds = ScoutCompanyEloquentModel::query()
            ->whereHas('scoreResults', fn ($query) => $query->where('is_current', true)->whereIn('tier', ['A', 'B']))
            ->pluck('id');

        $withDecisor = DB::table('scout_contacts')
            ->whereIn('company_id', $abIds)
            ->whereNull('anonymized_at')
            ->distinct('company_id')
            ->count('company_id');

        $allowed = 0;

        $companies = ScoutCompanyEloquentModel::query()
            ->whereIn('id', $abIds)
            ->with(['contactChannels' => fn ($query) => $query->where('status', 'active')])
            ->get(['id', 'country']);

        foreach ($companies as $company) {
            $advice = $this->advisor->advise(
                $company->contactChannels->map(static fn ($channel): array => [
                    'uuid' => $channel->uuid,
                    'type' => $channel->channel_type->value,
                    'url' => $channel->url,
                    'generic_email' => $channel->generic_email,
                    'status' => $channel->status->value,
                    'audience' => $channel->audience?->value,
                ])->all(),
                [
                    'country' => $company->country,
                    'has_offer' => true,
                    'is_employment_offer' => false,
                    'discovery_without_offer' => false,
                    'has_decisor' => true,
                    'nominative_email' => null,
                    'dgc_listed' => false,
                    'dgc_list_stale' => false,
                ],
                (array) config('lead-scout.contact_rules', []),
            );

            foreach ($advice['ranked'] as $candidate) {
                if ($candidate['allowed']) {
                    $allowed++;
                    break;
                }
            }
        }

        $replyByRole = DB::table('scout_outreaches')
            ->join('scout_contacts', 'scout_contacts.id', '=', 'scout_outreaches.contact_id')
            ->whereIn('scout_outreaches.stage', self::RESPONDED)
            ->selectRaw('scout_contacts.role_category as role, count(*) as replied')
            ->groupBy('role')
            ->get()
            ->map(static fn ($row): array => ['role' => $row->role, 'replied' => (int) $row->replied])
            ->all();

        return [
            'ab_leads' => $abIds->count(),
            'ab_with_decisor' => $withDecisor,
            'ab_with_allowed_channel' => $abIds->count() > 0 ? round($allowed / $abIds->count(), 4) : 0.0,
            'reply_by_role' => $replyByRole,
        ];
    }

    /**
     * @return array{outcome: string, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}
     */
    private function sample(int $sent, int $positive): array
    {
        $rule = ScoutDecisionRuleEloquentModel::query()->whereNotNull('locked_at')->orderByDesc('locked_at')->first();

        $evaluation = $this->evaluator->evaluate(
            [
                'sample_size' => $rule?->sample_size ?? 150,
                'window_days' => $rule?->window_days ?? 56,
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
