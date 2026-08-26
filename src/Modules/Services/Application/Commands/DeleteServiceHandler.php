<?php

declare(strict_types=1);

namespace Modules\Services\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class DeleteServiceHandler
{
    /**
     * @throws ModelNotFoundException<ServiceEloquentModel>
     */
    public function handle(string $uuid): void
    {
        ServiceEloquentModel::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }
}
