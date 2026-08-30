<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;

final readonly class ListBlogCategoriesHandler
{
    public function __construct(private BlogCategoryRepositoryPort $blogCategories) {}

    public function handle(BlogCategoryFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->blogCategories->paginate($filters, $perPage);
    }
}
