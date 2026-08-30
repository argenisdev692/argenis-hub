<?php

declare(strict_types=1);

namespace Modules\Post\Application\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Post\Application\ReadModels\PostPublicReadModel;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Landing-page feed: `published` posts only, optionally scoped to one
 * category, no authentication required. Kept as its own query (rather than
 * reusing {@see ListPostsHandler}) since the trust boundary and column
 * allowlist genuinely diverge — this one is reachable by anonymous internet
 * traffic (BACKEND-PHP §7 Insecure Design).
 *
 * Cached, unlike the admin `ListPostsHandler`/`GetPostHandler`: this is hit by
 * anonymous traffic with no per-user throttle beyond the route's
 * `throttle:landing-public` (BACKEND-PHP §5 Cache Management). Every
 * Create/Update/Delete/Restore/Bulk handler busts the `posts_public` tag via
 * {@see PostPublicFeedCachePort}.
 *
 * Important: we cache already-mapped public arrays (+ pagination meta) — never
 * Eloquent models. Serializing models + accessors across Redis was a source of
 * sticky 500s after the first warm (same fix as blog categories / portfolios).
 */
final readonly class ListPublicPostsHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    /**
     * @return PaginatedDataCollection<int, PostPublicReadModel>
     */
    public function handle(?string $categoryUuid, int $perPage = 15): PaginatedDataCollection
    {
        $page = max((int) request()->integer('page', 1), 1);
        $cacheKey = "posts.public.category.{$categoryUuid}.page.{$page}.per_page.{$perPage}";

        /** @var array{items: list<array<string, mixed>>, total: int, per_page: int, current_page: int, path: string} $payload */
        $payload = $this->publicFeedCache->remember(
            $cacheKey,
            function () use ($categoryUuid, $perPage): array {
                $paginator = $this->posts->paginatePublic($categoryUuid, $perPage);

                /** @var list<array<string, mixed>> $items */
                $items = $paginator->getCollection()
                    ->map(static function (PostEloquentModel $model): array {
                        return PostPublicReadModel::fromModel($model)->toArray();
                    })
                    ->values()
                    ->all();

                return [
                    'items' => $items,
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'path' => $paginator->path(),
                ];
            },
        );

        $paginator = new LengthAwarePaginator(
            $payload['items'],
            $payload['total'],
            $payload['per_page'],
            $payload['current_page'],
            [
                'path' => $payload['path'],
                'query' => request()->query(),
            ],
        );

        return PostPublicReadModel::collect($paginator, PaginatedDataCollection::class);
    }
}
