<?php

declare(strict_types=1);

namespace Modules\Services\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Services\Application\DTOs\ServiceData;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class GetServiceHandler
{
    /**
     * @throws ModelNotFoundException<ServiceEloquentModel>
     */
    #[\NoDiscard('handle() returns the service.')]
    public function handle(string $uuid): ServiceData
    {
        // withTrashed(): the admin detail view must also reach a suspended
        // row — it is the only way to preview one before restoring it.
        $service = ServiceEloquentModel::withTrashed()->where('uuid', $uuid)->firstOrFail();

        return ServiceData::fromModel($service);
    }
}
