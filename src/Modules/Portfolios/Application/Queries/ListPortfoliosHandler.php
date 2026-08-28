<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Portfolios\Application\DTOs\PortfolioData;
use Modules\Portfolios\Application\DTOs\PortfolioFilterData;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Paginated, filtered admin list. The gallery is eager-loaded with explicit
 * columns so {@see PortfolioData::fromModel()} never lazy-loads per row
 * (`Model::shouldBeStrict()` would throw).
 */
final readonly class ListPortfoliosHandler
{
    #[\NoDiscard('handle() returns the paginated portfolio list.')]
    public function handle(PortfolioFilterData $filters): LengthAwarePaginator
    {
        return PortfolioEloquentModel::query()
            ->applyFilters($filters)
            ->with('media:id,portfolio_id,path,sort_order')
            ->select([
                'id', 'uuid', 'title', 'client_name', 'project_type', 'tech_stack',
                'live_url', 'published_at', 'is_public', 'cover_path', 'video_path',
                'description', 'sort_order', 'created_at', 'updated_at', 'deleted_at',
            ])
            ->paginate($filters->perPage, page: $filters->page)
            ->through(PortfolioData::fromModel(...));
    }
}
