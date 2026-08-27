<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\ContactSupport\Application\DTOs\ContactSupportData;
use Modules\ContactSupport\Application\DTOs\ContactSupportFilterData;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Paginated, filtered admin inbox. No relations are touched — {@see
 * ContactSupportData} never exposes the owner, so there is nothing to N+1 on.
 */
final readonly class ListContactSupportsHandler
{
    #[\NoDiscard('handle() returns the paginated request list.')]
    public function handle(ContactSupportFilterData $filters): LengthAwarePaginator
    {
        return ContactSupportEloquentModel::query()
            ->applyFilters($filters)
            ->select([
                'id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'subject',
                'message', 'sms_consent', 'readed', 'is_spam', 'spam_score', 'spam_reasons',
                'created_at', 'updated_at', 'deleted_at',
            ])
            ->paginate($filters->perPage, page: $filters->page)
            ->through(ContactSupportData::fromModel(...));
    }
}
