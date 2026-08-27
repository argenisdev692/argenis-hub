<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

final readonly class BulkRestoreContactSupportHandler
{
    /**
     * Mass soft restore over a UUID set. Paired with
     * {@see BulkDeleteContactSupportHandler}.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        return ContactSupportEloquentModel::withTrashed()->whereIn('uuid', $uuids)->restore();
    }
}
