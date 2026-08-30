<?php

declare(strict_types=1);

namespace Modules\Post\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Domain\Ports\PostRepositoryPort;

final readonly class ListPostsHandler
{
    public function __construct(private PostRepositoryPort $posts) {}

    public function handle(PostFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->posts->paginate($filters, $perPage);
    }
}
