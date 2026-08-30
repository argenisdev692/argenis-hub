<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of soft-deleted blog categories by UUID. Authorization
 * (permission:BULK_RESTORE_BLOG_CATEGORIES) is enforced at the route.
 */
final readonly class BulkRestoreBlogCategoriesHandler
{
    public function __construct(
        private BlogCategoryRepositoryPort $blogCategories,
        private BlogCategoryPublicFeedCachePort $feedCache,
    ) {}

    public function handle(BulkUuidsData $data): int
    {
        $count = DB::transaction(fn () => $this->blogCategories->bulkRestoreByUuid($data->uuids));

        $this->feedCache->flush();

        return $count;
    }
}
