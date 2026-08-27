<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

final readonly class BulkDeleteContactSupportHandler
{
    /**
     * Mass soft delete over a UUID set. Paired with
     * {@see BulkRestoreContactSupportHandler} so a bulk selection is never a
     * one-way trip.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        return ContactSupportEloquentModel::query()->whereIn('uuid', $uuids)->delete();
    }
}
