<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Blog\Application\DTOs\BlogCategoryData;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Shared\Domain\Ports\StoragePort;

/**
 * Updates an existing blog category. A new image replaces the previous R2 object
 * (the old key is deleted so the bucket never accumulates orphans); when no image
 * is uploaded the existing column is left untouched. Authorization
 * (permission:UPDATE_BLOG_CATEGORIES) is enforced at the route.
 */
final readonly class UpdateBlogCategoryHandler
{
    public function __construct(
        private BlogCategoryRepositoryPort $blogCategories,
        private StoragePort $storage,
        private BlogCategoryPublicFeedCachePort $feedCache,
    ) {}

    public function handle(BlogCategoryEloquentModel $category, BlogCategoryData $data): BlogCategoryEloquentModel
    {
        $attributes = [
            'blog_category_name' => $data->name,
            'blog_category_description' => $data->description,
        ];

        $previous = null;

        if ($data->image !== null) {
            /** @var string|null $previous */
            $previous = $category->blog_category_image;
            // Upload the replacement before the transaction — object storage is
            // not transactional and must stay out of the DB unit-of-work.
            $attributes['blog_category_image'] = $this->storage->putFile('blog-categories', $data->image, 'public');
        }

        $updated = DB::transaction(fn () => $this->blogCategories->update($category, $attributes));

        // Delete the superseded object only after the write commits, so a failed
        // update never orphans the row from its still-referenced image.
        if (is_string($previous) && $previous !== '' && $previous !== $updated->blog_category_image) {
            $this->storage->delete($previous);
        }

        $this->feedCache->flush();

        return $updated;
    }
}
