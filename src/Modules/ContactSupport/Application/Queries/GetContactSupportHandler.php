<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\ContactSupport\Application\DTOs\ContactSupportData;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Single inbox entry by public `uuid`. Reads the row with its soft-deleted
 * siblings included so the admin detail view can open a trashed request.
 */
final readonly class GetContactSupportHandler
{
    /**
     * @throws ModelNotFoundException<ContactSupportEloquentModel>
     */
    #[\NoDiscard('handle() returns the requested record.')]
    public function handle(string $uuid): ContactSupportData
    {
        $support = ContactSupportEloquentModel::withTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ContactSupportData::fromModel($support);
    }
}
