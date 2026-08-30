<?php

declare(strict_types=1);

namespace Modules\Post\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;

final readonly class GetPostHandler
{
    public function __construct(private PostRepositoryPort $posts) {}

    public function handle(string $uuid): PostEloquentModel
    {
        return $this->posts->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(PostEloquentModel::class, [$uuid]);
    }
}
