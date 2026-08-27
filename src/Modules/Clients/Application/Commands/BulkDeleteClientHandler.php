<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Commands;

use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

final readonly class BulkDeleteClientHandler
{
    /**
     * Mass soft delete over a UUID set. Paired with {@see BulkRestoreClientHandler}
     * so a bulk selection is never a one-way trip.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        return ClientEloquentModel::query()->whereIn('uuid', $uuids)->delete();
    }
}
