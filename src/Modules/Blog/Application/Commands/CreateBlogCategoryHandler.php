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
 * Persists a new blog category. When an image is uploaded it is stored on R2
 * with public visibility and the returned object key is written to the
 * `blog_category_image` column. Authorization (permission:CREATE_BLOG_CATEGORIES)
 * is enforced at the route — never inside this handler.
 */
final readonly class CreateBlogCategoryHandler
{
    public function __construct(
        private BlogCategoryRepositoryPort $blogCategories,
        private StoragePort $storage,
        private BlogCategoryPublicFeedCachePort $feedCache,
    ) {}

    #[\NoDiscard]
    public function handle(BlogCategoryData $data, int $userId): BlogCategoryEloquentModel
    {
        // Upload happens before the transaction — object storage is not
        // transactional and must never run inside the DB unit-of-work.
        $imagePath = $data->image !== null
            ? $this->storage->putFile('blog-categories', $data->image, 'public')
            : null;

        $category = DB::transaction(fn () => $this->blogCategories->create([
            'blog_category_name' => $data->name,
            'blog_category_description' => $data->description,
            'blog_category_image' => $imagePath,
            'user_id' => $userId,
        ]));

        $this->feedCache->flush();

        return $category;
    }
}
