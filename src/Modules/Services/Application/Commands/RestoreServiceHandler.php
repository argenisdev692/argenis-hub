<?php

declare(strict_types=1);

namespace Modules\Services\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Services\Application\DTOs\ServiceData;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class RestoreServiceHandler
{
    /**
     * @throws ModelNotFoundException<ServiceEloquentModel>
     */
    #[\NoDiscard('handle() returns the restored service.')]
    public function handle(string $uuid): ServiceData
    {
        $service = ServiceEloquentModel::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $service->restore();

        return ServiceData::fromModel($service);
    }
}
