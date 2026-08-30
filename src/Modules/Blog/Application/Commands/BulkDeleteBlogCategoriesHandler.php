<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of blog categories by UUID. Authorization
 * (permission:BULK_DELETE_BLOG_CATEGORIES) is enforced at the route — never
 * inside this handler (no god-handler).
 */
final readonly class BulkDeleteBlogCategoriesHandler
{
    public function __construct(
        private BlogCategoryRepositoryPort $blogCategories,
        private BlogCategoryPublicFeedCachePort $feedCache,
    ) {}

    public function handle(BulkUuidsData $data): int
    {
        $count = DB::transaction(fn () => $this->blogCategories->bulkSoftDeleteByUuid($data->uuids));

        $this->feedCache->flush();

        return $count;
    }
}
