<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\LeadScout\Application\DTOs\LeadFilterData;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

/**
 * Prioritized bandeja read (spec US-5, T060): single filtered query with
 * the current score eager-loaded on explicit columns. Discarded leads
 * read through the same filter with `tier[]=discarded` + reason.
 */
final readonly class ListLeadsHandler
{
    /**
     * @return LengthAwarePaginator<int, ScoutCompanyEloquentModel>
     */
    #[\NoDiscard]
    public function handle(LeadFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return ScoutCompanyEloquentModel::query()
            ->applyFilters($filters)
            ->with(['scoreResults' => fn ($query) => $query
                ->where('is_current', true)
                ->select(['id', 'company_id', 'tier', 'lead_score', 'confidence'])])
            ->select(['scout_companies.id', 'scout_companies.uuid', 'name', 'canonical_domain', 'country', 'company_type', 'origin', 'discovery_wave', 'needs_research', 'activity_status', 'has_decision_maker', 'scout_companies.created_at', 'scout_companies.updated_at'])
            // US-5 CA-1: tier, Lead Score, confidence — unscored sinks below.
            ->leftJoin('scout_score_results as csr', function ($join): void {
                $join->on('csr.company_id', '=', 'scout_companies.id')->where('csr.is_current', true);
            })
            ->orderByRaw("case csr.tier when 'A' then 0 when 'B' then 1 when 'C' then 2 when 'discarded' then 3 else 4 end")
            ->orderByDesc('csr.lead_score')
            ->orderByDesc('csr.confidence')
            ->orderByDesc('scout_companies.created_at')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }
}
