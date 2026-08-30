<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of posts by UUID. Authorization
 * (permission:BULK_DELETE_POSTS) is enforced at the route.
 */
final readonly class BulkDeletePostsHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    public function handle(BulkUuidsData $data): int
    {
        $count = DB::transaction(fn () => $this->posts->bulkSoftDeleteByUuid($data->uuids));

        $this->publicFeedCache->flush();

        return $count;
    }
}
