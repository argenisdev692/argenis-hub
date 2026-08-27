<?php

declare(strict_types=1);

namespace Modules\Clients\Application\Commands;

use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

final readonly class BulkRestoreClientHandler
{
    /**
     * Mass soft restore over a UUID set. Paired with {@see BulkDeleteClientHandler}.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        return ClientEloquentModel::withTrashed()->whereIn('uuid', $uuids)->restore();
    }
}
