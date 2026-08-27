<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

final readonly class DeleteContactSupportHandler
{
    /**
     * @throws ModelNotFoundException<ContactSupportEloquentModel>
     */
    public function handle(string $uuid): void
    {
        ContactSupportEloquentModel::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }
}
