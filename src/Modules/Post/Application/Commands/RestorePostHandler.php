<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;

/**
 * Restores a soft-deleted post by UUID. Authorization (permission:RESTORE_POSTS)
 * is enforced at the route.
 */
final readonly class RestorePostHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    public function handle(string $uuid): bool
    {
        $result = DB::transaction(fn () => $this->posts->restore($uuid));

        $this->publicFeedCache->flush();

        return $result;
    }
}
