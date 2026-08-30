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
final class EloquentBlogCategoryRepository implements BlogCategoryRepositoryPort
{
    use BulkSoftDeletesByUuid;

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
                ->orderByDesc('published_at')])
            ->withCount('publishedPosts as published_posts_count')
            ->orderBy('blog_category_name')
            ->limit(100)
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
