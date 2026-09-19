<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\ReadRepositories;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Modules\LeadScout\Domain\Ports\LeadReadRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\LeadCriteria;
use Modules\LeadScout\Domain\ValueObjects\LeadPage;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\CompanyMapper;

/**
 * Single filtered query with the current score eager-loaded on explicit
 * columns (spec US-5, T060): count + page + scores, whatever the page size.
 */
final readonly class EloquentLeadReadRepository implements LeadReadRepositoryPort
{
    public function page(LeadCriteria $criteria, int $perPage): LeadPage
    {
        $page = ScoutCompanyEloquentModel::query()
            ->applyFilters($criteria)
            ->with(['scoreResults' => fn (HasMany $query): HasMany => $query
                ->where('is_current', true)
                ->select(['id', 'company_id', 'tier', 'lead_score', 'confidence'])])
            ->select('scout_companies.*')
            // US-5 CA-1: tier, Lead Score, confidence — unscored sinks below.
            ->leftJoin('scout_score_results as csr', static function (JoinClause $join): void {
                $join->on('csr.company_id', '=', 'scout_companies.id')->where('csr.is_current', true);
            })
            ->orderByRaw("case csr.tier when 'A' then 0 when 'B' then 1 when 'C' then 2 when 'discarded' then 3 else 4 end")
            ->orderByDesc('csr.lead_score')
            ->orderByDesc('csr.confidence')
            ->orderByDesc('scout_companies.created_at')
            ->paginate(min(max($perPage, 1), 100));

        $items = [];

        foreach ($page->items() as $company) {
            $current = $company->scoreResults->first();

            $items[] = [
                'company' => CompanyMapper::toEntity($company),
                'tier' => $current?->tier,
                'leadScore' => $current?->lead_score,
                'confidence' => $current?->confidence,
            ];
        }

        return new LeadPage($items, $page->currentPage(), $page->perPage(), $page->total());
    }
}
