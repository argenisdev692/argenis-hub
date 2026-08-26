<?php

declare(strict_types=1);

namespace Modules\Services\Application\Commands;

use Modules\Services\Infrastructure\Cache\ServicePublicFeedCache;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

final readonly class BulkRestoreServiceHandler
{
    /**
     * Mass restore bypasses model events, so the public feed cache is flushed
     * explicitly here instead of relying on the `saved` hook in {@see
     * ServiceEloquentModel::booted()}.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        $restored = ServiceEloquentModel::withTrashed()->whereIn('uuid', $uuids)->restore();

        ServicePublicFeedCache::flush();

        return $restored;
    }
}
