<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\ReadRepositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Ports\FunnelMetricsReadPort;
use Modules\LeadScout\Domain\ValueObjects\MetricsPeriod;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Report-shaped reads for the funnel dashboard: the shape diverges from
 * every write aggregate, so it lives apart (ReadRepository rule). All
 * aggregation happens in SQL over explicit columns.
 */
final readonly class EloquentFunnelMetricsReadRepository implements FunnelMetricsReadPort
{
    public function stageCounts(MetricsPeriod $period): array
    {
        return $this->outreaches($period)
            ->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->map(static fn ($count, $stage): array => ['stage' => (string) $stage, 'count' => (int) $count])
            ->values()
            ->all();
    }

    public function totals(MetricsPeriod $period): array
    {
        $outreaches = $this->outreaches($period);

        return [
            'sent' => (clone $outreaches)->whereNotNull('sent_at')->count(),
            'responded' => (clone $outreaches)->whereIn('stage', self::stageValues(OutreachStage::respondedOutcomes()))->count(),
            'positive' => (clone $outreaches)->whereIn('stage', self::stageValues(OutreachStage::positiveOutcomes()))->count(),
        ];
    }

    public function byChannel(MetricsPeriod $period): array
    {
        return $this->groupedOutreaches($period, 'send_medium');
    }

    public function byVariant(MetricsPeriod $period): array
    {
        return $this->groupedOutreaches($period, 'variant');
    }

    public function byOrigin(MetricsPeriod $period): array
    {
        return $this->groupedByCompany($period, 'origin');
    }

    public function byCountry(MetricsPeriod $period): array
    {
        return $this->groupedByCompany($period, 'country');
    }

    public function waveQuality(MetricsPeriod $period): array
    {
        $companies = ScoutCompanyEloquentModel::query()
            ->with(['scoreResults' => fn ($query) => $query->where('is_current', true)])
            ->tap(fn (Builder $query) => $this->withinPeriod($query, 'created_at', $period))
            ->get(['id', 'discovery_wave', 'origin']);

        $responded = self::stageValues(OutreachStage::respondedOutcomes());
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
            $answered = ScoutOutreachEloquentModel::query()->whereIn('company_id', $ids)->whereIn('stage', $responded)->count();

            $out[] = [
                'wave' => (string) $wave,
                'companies' => $group->count(),
                'discarded_by_reason' => $discarded,
                'ab_leads' => $ab,
                'response_rate' => $sent > 0 ? round($answered / $sent, 4) : 0.0,
            ];
        }

        return $out;
    }

    public function weeklyVolume(MetricsPeriod $period): array
    {
        $week = DB::getDriverName() === 'pgsql'
            ? "to_char(scout_job_postings.created_at, 'IYYY-IW')"
            : "strftime('%Y-%W', scout_job_postings.created_at)";

        return DB::table('scout_job_postings')
            ->join('scout_job_posting_sources', 'scout_job_posting_sources.posting_id', '=', 'scout_job_postings.id')
            ->join('scout_sources', 'scout_sources.id', '=', 'scout_job_posting_sources.source_id')
            ->tap(fn (QueryBuilder $query) => $this->withinPeriod($query, 'scout_job_postings.created_at', $period))
            ->selectRaw("{$week} as week, scout_sources.name as source, count(distinct scout_job_postings.id) as postings")
            ->groupBy('week', 'source')
            ->orderBy('week')
            ->get()
            ->map(static fn ($row): array => ['week' => (string) $row->week, 'source' => (string) $row->source, 'postings' => (int) $row->postings])
            ->all();
    }

    public function billing(MetricsPeriod $period): array
    {
        $query = ScoutOpportunityEloquentModel::query()
            ->where('status', 'won')
            ->tap(fn (Builder $query) => $this->withinPeriod($query, 'created_at', $period));

        return [
            'hours_billed' => (int) (clone $query)->sum('hours_per_month'),
            'revenue_cents' => (int) (clone $query)->sum('amount_cents'),
        ];
    }

    public function costPerQualifiedLead(): array
    {
        $spent = (int) DB::table('scout_budgets')->sum('spent_micros');
        $qualified = $this->abCompanies()->count();

        return [
            'spent_micros' => $spent,
            'qualified_leads' => $qualified,
            'micros_per_lead' => $qualified > 0 ? (int) round($spent / $qualified) : null,
        ];
    }

    public function searchEffectiveness(): array
    {
        $families = DB::table('scout_search_queries')
            ->selectRaw('family, count(*) as queries, coalesce(sum(json_array_length(results)), 0) as results, coalesce(sum(new_companies_count), 0) as new_companies')
            ->groupBy('family')
            ->get();

        return $families->map(function ($row): array {
            $texts = DB::table('scout_search_queries')->where('family', $row->family)->pluck('query_text');

            return [
                'family' => $row->family,
                'queries' => (int) $row->queries,
                'results' => (int) $row->results,
                'new_companies' => (int) $row->new_companies,
                'ab_leads' => $this->abCompanies()
                    ->where('origin', 'discovery')
                    ->whereIn('origin_ref', $texts->all())
                    ->count(),
            ];
        })->all();
    }

    public function extraction(): array
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

    public function abCompaniesWithActiveChannels(): array
    {
        return $this->abCompanies()
            ->with(['contactChannels' => fn ($query) => $query->where('status', 'active')])
            ->get(['id', 'country'])
            ->map(static fn (ScoutCompanyEloquentModel $company): array => [
                'country' => $company->country,
                'channels' => $company->contactChannels->map(static fn ($channel): array => [
                    'uuid' => $channel->uuid,
                    'type' => $channel->channel_type->value,
                    'url' => $channel->url,
                    'generic_email' => $channel->generic_email,
                    'status' => $channel->status->value,
                    'audience' => $channel->audience?->value,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function abCompaniesWithDecisor(): int
    {
        return DB::table('scout_contacts')
            ->whereIn('company_id', $this->abCompanies()->select('id'))
            ->whereNull('anonymized_at')
            ->distinct('company_id')
            ->count('company_id');
    }

    public function repliesByRole(): array
    {
        return DB::table('scout_outreaches')
            ->join('scout_contacts', 'scout_contacts.id', '=', 'scout_outreaches.contact_id')
            ->whereIn('scout_outreaches.stage', self::stageValues(OutreachStage::respondedOutcomes()))
            ->selectRaw('scout_contacts.role_category as role, count(*) as replied')
            ->groupBy('role')
            ->get()
            ->map(static fn ($row): array => ['role' => $row->role, 'replied' => (int) $row->replied])
            ->all();
    }

    /**
     * @return Builder<ScoutOutreachEloquentModel>
     */
    private function outreaches(MetricsPeriod $period): Builder
    {
        return ScoutOutreachEloquentModel::query()
            ->tap(fn (Builder $query) => $this->withinPeriod($query, 'scout_outreaches.created_at', $period));
    }

    /**
     * @return Builder<ScoutCompanyEloquentModel>
     */
    private function abCompanies(): Builder
    {
        return ScoutCompanyEloquentModel::query()
            ->whereHas('scoreResults', fn ($query) => $query->where('is_current', true)->whereIn('tier', ['A', 'B']));
    }

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    private function groupedOutreaches(MetricsPeriod $period, string $column): array
    {
        return self::rateRows($this->outreaches($period)
            ->selectRaw("{$column} as value, count(*) as sent, ".self::stageSums('stage'))
            ->groupBy($column)
            ->get());
    }

    /**
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    private function groupedByCompany(MetricsPeriod $period, string $column): array
    {
        return self::rateRows($this->outreaches($period)
            ->join('scout_companies', 'scout_companies.id', '=', 'scout_outreaches.company_id')
            ->selectRaw("scout_companies.{$column} as value, count(*) as sent, ".self::stageSums('scout_outreaches.stage'))
            ->groupBy("scout_companies.{$column}")
            ->get());
    }

    private static function stageSums(string $stageColumn): string
    {
        $responded = implode("','", self::stageValues(OutreachStage::respondedOutcomes()));
        $positive = implode("','", self::stageValues(OutreachStage::positiveOutcomes()));

        return "sum(case when {$stageColumn} in ('{$responded}') then 1 else 0 end) as responded, "
            ."sum(case when {$stageColumn} in ('{$positive}') then 1 else 0 end) as positive";
    }

    /**
     * @param  iterable<object>  $rows
     * @return list<array{value: ?string, sent: int, responded: int, positive: int, response_rate: float}>
     */
    private static function rateRows(iterable $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $value = $row->value;

            $out[] = [
                'value' => $value instanceof \BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value),
                'sent' => (int) $row->sent,
                'responded' => (int) $row->responded,
                'positive' => (int) $row->positive,
                'response_rate' => $row->sent > 0 ? round($row->responded / $row->sent, 4) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  list<OutreachStage>  $stages
     * @return list<string>
     */
    private static function stageValues(array $stages): array
    {
        return array_map(static fn (OutreachStage $stage): string => $stage->value, $stages);
    }

    /**
     * @param  Builder<Model>|QueryBuilder  $query
     */
    private function withinPeriod(Builder|QueryBuilder $query, string $column, MetricsPeriod $period): void
    {
        if ($period->from !== null) {
            $query->where($column, '>=', $period->from);
        }

        if ($period->to !== null) {
            $query->where($column, '<=', $period->to);
        }
    }
}
