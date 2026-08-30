<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;

/**
 * Restores a soft-deleted blog category by UUID. Authorization
 * (permission:RESTORE_BLOG_CATEGORIES) is enforced at the route.
 */
final readonly class RestoreBlogCategoryHandler
{
    public function __construct(
        private BlogCategoryRepositoryPort $blogCategories,
        private BlogCategoryPublicFeedCachePort $feedCache,
    ) {}

    public function handle(string $uuid): bool
    {
        $result = DB::transaction(fn () => $this->blogCategories->restore($uuid));

        $this->feedCache->flush();

        return $result;
    }
}
