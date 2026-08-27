<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Clients\Application\DTOs\ClientData;
use Modules\Clients\Application\DTOs\ClientFilterData;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * Paginated, filtered admin list. No relations are touched — {@see ClientData}
 * never exposes the owner, so there is nothing here to N+1 on.
 */
final readonly class ListClientsHandler
{
    #[\NoDiscard('handle() returns the paginated client list.')]
    public function handle(ClientFilterData $filters): LengthAwarePaginator
    {
        return ClientEloquentModel::query()
            ->applyFilters($filters)
            ->select([
                'id', 'uuid', 'client_name', 'email', 'status', 'phone', 'address',
                'country', 'country_code', 'tax_id', 'nif', 'website', 'facebook_link',
                'instagram_link', 'linkedin_link', 'twitter_link', 'notes',
                'created_at', 'updated_at', 'deleted_at',
            ])
            ->paginate($filters->perPage, page: $filters->page)
            ->through(ClientData::fromModel(...));
    }
}
