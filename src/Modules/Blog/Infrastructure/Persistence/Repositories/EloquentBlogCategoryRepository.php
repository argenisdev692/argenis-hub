<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see BlogCategoryRepositoryPort}. Bulk soft-delete/restore
 * are inherited from the Shared {@see BulkSoftDeletesByUuid} trait (DRY).
 */
final readonly class EloquentBlogCategoryRepository implements BlogCategoryRepositoryPort
{
    use BulkSoftDeletesByUuid;

    /**
     * Hard caps on the anonymous landing-page feed (OWASP API4). Kept here — the
     * only place that composes that query — rather than in the handler, so the
     * bound cannot be bypassed by a second caller of `listPublic()`.
     */
    private const int MAX_PUBLIC_CATEGORIES = 100;

    private const int MAX_PUBLIC_POSTS_PER_CATEGORY = 12;

    /**
     * @return class-string<BlogCategoryEloquentModel>
     */
    protected function model(): string
    {
        return BlogCategoryEloquentModel::class;
    }

    public function paginate(BlogCategoryFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return BlogCategoryEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->select([
                'id',
                'uuid',
                'blog_category_name',
                'blog_category_description',
                'blog_category_image',
                'user_id',
                'created_at',
                'deleted_at',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Landing-page feed: every active category, each with its published posts
     * embedded (the JSON relationship the landing page renders) plus the count.
     * `publishedPosts` already filters `post_status = published`; the Post
     * SoftDeletes global scope adds `deleted_at is null`. Column-scoped eager
     * load + `withCount` keep it N+1-free (BACKEND-PHP §4.1).
     *
     * BOTH axes of this anonymous response are bounded (OWASP API4 —
     * unrestricted resource consumption): at most {@see self::MAX_PUBLIC_CATEGORIES}
     * categories, each embedding at most {@see self::MAX_PUBLIC_POSTS_PER_CATEGORY}
     * of its newest published posts. The per-parent limit is applied inside the
     * eager-load closure (Laravel 11+ resolves it with a window function, so it
     * stays one query). `published_posts_count` comes from `withCount` and
     * therefore still reports the TRUE total, so a "view all" affordance can
     * show the real number while the payload stays bounded.
     *
     * @return Collection<int, BlogCategoryEloquentModel>
     */
    public function listPublic(): Collection
    {
        return BlogCategoryEloquentModel::query()
            ->select([
                'id',
                'uuid',
                'blog_category_name',
                'blog_category_description',
                'blog_category_image',
            ])
            ->with(['publishedPosts' => static fn ($q) => $q
                ->select([
                    'id',
                    'uuid',
                    'category_id',
                    'post_title',
                    'post_title_slug',
                    'post_excerpt',
                    'post_cover_image',
                    'published_at',
                ])
                ->orderByDesc('published_at')
                ->limit(self::MAX_PUBLIC_POSTS_PER_CATEGORY)])
            ->withCount('publishedPosts as published_posts_count')
            ->orderBy('blog_category_name')
            ->limit(self::MAX_PUBLIC_CATEGORIES)
            ->get();
    }

    public function findByUuid(string $uuid): ?BlogCategoryEloquentModel
    {
        return BlogCategoryEloquentModel::withTrashed()
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $attributes): BlogCategoryEloquentModel
    {
        return BlogCategoryEloquentModel::query()->create($attributes);
    }

    public function update(BlogCategoryEloquentModel $category, array $attributes): BlogCategoryEloquentModel
    {
        $category->update($attributes);

        return $category->refresh();
    }

    public function softDelete(string $uuid): bool
    {
        return (bool) BlogCategoryEloquentModel::query()->where('uuid', $uuid)->delete();
    }

    public function restore(string $uuid): bool
    {
        return (bool) BlogCategoryEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }
}
