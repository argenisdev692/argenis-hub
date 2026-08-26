<?php

declare(strict_types=1);

namespace Modules\Services\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Services\Application\DTOs\ServiceData;
use Modules\Services\Application\DTOs\ServiceFilterData;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * Paginated, filtered admin list. No relations are touched — the response
 * shape never exposes the owner, so there is nothing here to N+1 on.
 */
final readonly class ListServicesHandler
{
    #[\NoDiscard('handle() returns the paginated service list.')]
    public function handle(ServiceFilterData $filters): LengthAwarePaginator
    {
        return ServiceEloquentModel::query()
            ->applyFilters($filters)
            ->select(['id', 'uuid', 'name', 'slug', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at', 'deleted_at'])
            ->paginate($filters->perPage, page: $filters->page)
            ->through(ServiceData::fromModel(...));
    }
}
