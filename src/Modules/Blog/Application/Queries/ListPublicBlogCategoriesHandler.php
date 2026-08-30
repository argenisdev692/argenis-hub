<?php

declare(strict_types=1);

namespace Modules\Blog\Application\Queries;

use Modules\Blog\Application\ReadModels\BlogCategoryPublicReadModel;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

/**
 * Landing-page category selector: every active category with its published
 * posts embedded (JSON relationship) and their count, no authentication
 * required. Kept as its own query (rather than reusing
 * {@see ListBlogCategoriesHandler}) since the trust boundary and column
 * allowlist genuinely diverge — this one is reachable by anonymous internet
 * traffic (BACKEND-PHP §7 Insecure Design).
 *
 * Cached via {@see BlogCategoryPublicFeedCachePort}, unlike the admin list:
 * this is hit by anonymous traffic with no per-user throttle beyond the route's
 * `throttle:landing-public` (BACKEND-PHP §5 Cache Management). Every
 * category-mutating handler AND every Post-mutating handler busts the feed,
 * since the embedded posts depend on both.
 *
 * Important: we cache the already-mapped public arrays — never Eloquent models.
 * Serializing models + accessors (R2) across Redis was a source of sticky 500s
 * after the first warm.
 */
final readonly class ListPublicBlogCategoriesHandler
{
    private const string CACHE_KEY = 'blog_categories.public';

    public function __construct(
        private BlogCategoryRepositoryPort $categories,
        private BlogCategoryPublicFeedCachePort $feedCache,
    ) {}

    /**
     * @return list<array{uuid: string, name: string|null, description: string|null, image_url: string|null, posts_count: int, posts: list<array<string, mixed>>}>
     */
    public function handle(): array
    {
        /** @var list<array{uuid: string, name: string|null, description: string|null, image_url: string|null, posts_count: int, posts: list<array<string, mixed>>}> */
        return $this->feedCache->remember(
            self::CACHE_KEY,
            fn (): array => $this->categories
                ->listPublic()
                ->map(static fn (BlogCategoryEloquentModel $model): array => BlogCategoryPublicReadModel::fromModel($model)->toArray())
                ->values()
                ->all(),
        );
    }
}
