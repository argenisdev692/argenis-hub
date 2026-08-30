<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;

/**
 * Soft-deletes a single post by UUID. The cover image is intentionally kept so
 * a restore is lossless. Authorization (permission:DELETE_POSTS) is enforced
 * at the route.
 */
final readonly class DeletePostHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    public function handle(string $uuid): bool
    {
        $result = DB::transaction(fn () => $this->posts->softDelete($uuid));

        $this->publicFeedCache->flush();

        return $result;
    }
}
