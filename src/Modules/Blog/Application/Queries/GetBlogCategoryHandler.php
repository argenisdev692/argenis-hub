<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

final readonly class GetBlogCategoryHandler
{
    public function __construct(private BlogCategoryRepositoryPort $blogCategories) {}

    public function handle(string $uuid): BlogCategoryEloquentModel
    {
        return $this->blogCategories->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(BlogCategoryEloquentModel::class, [$uuid]);
    }
}
