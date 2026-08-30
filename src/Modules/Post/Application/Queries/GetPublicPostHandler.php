<?php

declare(strict_types=1);

namespace Modules\Post\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Post\Application\ReadModels\PostPublicReadModel;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;

/**
 * Single-post landing-page detail view (by slug): `published` only, no
 * authentication required. Mirrors {@see ListPublicPostsHandler}'s trust
 * boundary and caching rationale, but includes the full `content` field.
 *
 * Cached payload is a mapped {@see PostPublicReadModel} array — never an
 * Eloquent model (avoids sticky Redis 500s after the first warm).
 */
final readonly class GetPublicPostHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    public function handle(string $slug): PostPublicReadModel
    {
        $cacheKey = "posts.public.slug.{$slug}";

        /** @var array<string, mixed>|null $payload */
        $payload = $this->publicFeedCache->remember(
            $cacheKey,
            function () use ($slug): ?array {
                $post = $this->posts->findPublicBySlug($slug);

                if (! $post instanceof PostEloquentModel) {
                    return null;
                }

                return PostPublicReadModel::fromModel($post, includeContent: true)->toArray();
            },
        );

        if ($payload === null) {
            throw (new ModelNotFoundException)->setModel(PostEloquentModel::class, [$slug]);
        }

        return PostPublicReadModel::from($payload);
    }
}
