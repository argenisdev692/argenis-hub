<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\ContactSupport\Application\DTOs\ContactSupportData;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

final readonly class RestoreContactSupportHandler
{
    /**
     * @throws ModelNotFoundException<ContactSupportEloquentModel>
     */
    #[\NoDiscard('handle() returns the restored request.')]
    public function handle(string $uuid): ContactSupportData
    {
        $support = ContactSupportEloquentModel::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $support->restore();

        return ContactSupportData::fromModel($support);
    }
}
