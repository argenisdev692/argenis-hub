<?php

declare(strict_types=1);

namespace Modules\Services\Application\Commands;

use Modules\Services\Infrastructure\Cache\ServicePublicFeedCache;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class BulkDeleteServiceHandler
{
    /**
     * Mass soft delete bypasses model events, so the public feed cache is
     * flushed explicitly here instead of relying on the `deleted` hook in
     * {@see ServiceEloquentModel::booted()}.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        $deleted = ServiceEloquentModel::query()->whereIn('uuid', $uuids)->delete();

        ServicePublicFeedCache::flush();

        return $deleted;
    }
}
